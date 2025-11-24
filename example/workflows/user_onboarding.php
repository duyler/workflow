<?php

declare(strict_types=1);

use Duyler\Workflow\DSL\RetryBackoff;
use Duyler\Workflow\DSL\Step;
use Duyler\Workflow\DSL\Workflow;

return Workflow::define('UserOnboarding')
    ->description('User registration and onboarding workflow with email verification')
    ->sequence(
        Step::withId('validate_registration')
            ->actions(['User.ValidateData'])
            ->timeout(10)
            ->onSuccess('create_account')
            ->onFail('validation_failed'),
        Step::withId('create_account')
            ->actions(['User.Create'])
            ->retry(2, 2, RetryBackoff::Fixed)
            ->onSuccess('send_verification')
            ->onFail('creation_failed'),
        Step::withId('send_verification')
            ->actions(['Email.SendVerification'])
            ->retry(3, 5, RetryBackoff::Exponential)
            ->onSuccess('wait_for_verification'),
        Step::withId('wait_for_verification')
            ->actions(['User.CheckVerification'])
            ->delay(86400)
            ->when('result.verified == true', 'complete_onboarding')
            ->when('result.expired == true', 'verification_expired')
            ->onFail('verification_check_failed'),
        Step::withId('complete_onboarding')
            ->parallel([
                'User.SetupProfile',
                'User.SendWelcomeEmail',
                'Analytics.TrackOnboarding',
            ])
            ->onSuccess('onboarding_complete'),
        Step::withId('onboarding_complete')
            ->actions(['User.MarkActive'])
            ->isFinal(),
        Step::withId('verification_expired')
            ->actions(['User.SendExpirationNotice'])
            ->onSuccess('send_verification'),
        Step::withId('verification_check_failed')
            ->actions(['User.LogError'])
            ->isFinal(),
        Step::withId('validation_failed')
            ->actions(['User.SendValidationErrors'])
            ->isFinal(),
        Step::withId('creation_failed')
            ->actions(['User.NotifyCreationFailure'])
            ->isFinal(),
    );
