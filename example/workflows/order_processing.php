<?php

declare(strict_types=1);

use Duyler\Workflow\DSL\RetryBackoff;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;

return Workflow::define('OrderProcessing')
    ->description('Complete order processing workflow with payment, inventory, and shipping')
    ->sequence(
        Step::withId('validate_order')
            ->actions(['Order.Validate'])
            ->timeout(30)
            ->onSuccess('check_inventory')
            ->onFail('order_failed'),
        Step::withId('check_inventory')
            ->parallel([
                'Inventory.CheckAvailability',
                'Pricing.CalculateTotal',
            ])
            ->timeout(60)
            ->onSuccess('process_payment')
            ->onFail('insufficient_inventory'),
        Step::withId('process_payment')
            ->actions(['Payment.Charge'])
            ->retry(3, 5, RetryBackoff::Exponential)
            ->timeout(120)
            ->when('result.amount > 10000', 'manual_approval')
            ->onSuccess('reserve_inventory')
            ->onFail('payment_failed'),
        Step::withId('manual_approval')
            ->actions(['Order.RequestApproval'])
            ->delay(300)
            ->onSuccess('reserve_inventory')
            ->onFail('order_cancelled'),
        Step::withId('reserve_inventory')
            ->actions(['Inventory.Reserve'])
            ->retry(2, 3, RetryBackoff::Fixed)
            ->onSuccess('ship_order')
            ->onFail('refund_payment'),
        Step::withId('ship_order')
            ->actions(['Shipping.CreateLabel', 'Shipping.Schedule'])
            ->onSuccess('notify_customer')
            ->onFail('shipping_error'),
        Step::withId('notify_customer')
            ->actions(['Notification.SendOrderConfirmation'])
            ->onSuccess('order_completed'),
        Step::withId('order_completed')
            ->actions(['Order.MarkCompleted'])
            ->isFinal(),
        Step::withId('insufficient_inventory')
            ->actions(['Order.NotifyOutOfStock'])
            ->isFinal(),
        Step::withId('payment_failed')
            ->actions(['Order.NotifyPaymentFailed'])
            ->isFinal(),
        Step::withId('order_cancelled')
            ->actions(['Order.Cancel'])
            ->isFinal(),
        Step::withId('refund_payment')
            ->actions(['Payment.Refund'])
            ->onSuccess('order_failed')
            ->onFail('manual_intervention'),
        Step::withId('shipping_error')
            ->actions(['Shipping.HandleError'])
            ->retry(2, 10, RetryBackoff::Linear)
            ->onSuccess('notify_customer')
            ->onFail('manual_intervention'),
        Step::withId('manual_intervention')
            ->actions(['Order.FlagForReview'])
            ->isFinal(),
        Step::withId('order_failed')
            ->actions(['Order.MarkFailed'])
            ->isFinal(),
    );
