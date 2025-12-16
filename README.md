# Duyler Workflow

FSM-based workflow engine for long-running business processes in Duyler framework.

## Overview

Workflow package provides FSM (Finite State Machine) implementation for managing long-running business processes that can span hours, days, or even weeks. Unlike the existing Scenario package (designed for single HTTP requests), Workflow is built for worker environments with persistent state management.

## Key Features

-  **Long-running processes** - workflows can run for days or weeks
-  **State persistence** - workflow state is saved after each step
-  **Delays** - schedule steps to execute after a delay
-  **Timeouts** - set execution time limits for steps
-  **Retry with backoff** - automatic retry with exponential/linear/fixed backoff
-  **Conditional transitions** - dynamic routing based on results
-  **Parallel execution** - run multiple actions simultaneously
-  **Saga pattern** - compensations for rollback on failures
-  **Event Bus integration** - seamless integration via State Handlers
-  **JSON serialization** - visualization on frontend (graphs, blocks)
-  **Business-friendly** - processes are understandable for non-technical users
-  **Visual workflow builder** (future) - create workflows via UI

## Quick Example

```php
// workflow/process_order.php

return Workflow::define('ProcessOrder')
    ->description('Complete order processing from validation to delivery')
    ->route(HttpMethod::Post, '/api/orders')
    ->timeout(hours: 24)
    ->sequence(
        // Step 1: Validate order
        Step::withId('validate_order')
            ->actions([Order::Validate])
            ->timeout(seconds: 30)
            ->onSuccess('reserve_inventory')
            ->onFail('reject_order'),
        
        // Step 2: Reserve inventory
        Step::withId('reserve_inventory')
            ->actions([Inventory::Reserve])
            ->compensate([Inventory::Release]) // rollback on failure
            ->retry(maxAttempts: 3, delaySeconds: 10, backoff: 'exponential')
            ->onSuccess('process_payment')
            ->onFail('notify_failed'),
        
        // Step 3: Process payment
        Step::withId('process_payment')
            ->actions([Payment::Charge])
            ->compensate([Payment::Refund])
            ->when(
                fn($result) => $result->amount > 10000,
                'manual_review' // large amounts need manual review
            )
            ->onSuccess('confirm_order')
            ->onFail('release_and_refund'),
        
        // Step 4: Confirm order
        Step::withId('confirm_order')
            ->delay(seconds: 10) // wait 10 seconds
            ->parallel([
                Email::SendConfirmation,
                SMS::SendNotification,
                Analytics::Track,
            ])
            ->isFinal(),
    );
```

## Core Concepts

### Workflow
FSM definition with steps and transitions.

### Step
A state in FSM with actions to execute and transitions to other states.

### WorkflowState
Persistent state of a workflow instance (current step, context data, history).

### WorkflowContext
Shared data between steps.

### Transition
Routing logic between steps based on results (success/fail/conditions).

## How It Works

1. **Compilation**: DSL → optimized state graph (once at startup)
2. **Start**: Trigger → create WorkflowState → execute first step
3. **Execution Loop** (Event Bus cycles):
   - **tick()** - called on each Event Bus iteration
     - WorkflowManager checks delays, timeouts, conditions
     - Schedules actions via ExecutorInterface
   - **actionReceived()** - called when action completes
     - WorkflowManager determines next step
     - Updates and saves state
4. **Transition**: Based on action results (success/fail/conditions)
5. **Delays**: Steps scheduled for future execution
6. **Completion**: Final step → status = Completed

### Integration Points

```php
// Framework State Handlers (not part of Workflow package!)

// MainCyclicStateHandler - calls tick() on each iteration
final class WorkflowCyclicHandler implements MainCyclicStateHandlerInterface
{
    public function handle(...): void
    {
        $this->workflowManager->tick(); // ← Manager decides what to do
    }
}

// MainAfterStateHandler - calls actionReceived() when action completes
final class WorkflowActionHandler implements MainAfterStateHandlerInterface
{
    public function handle(...): void
    {
        $this->workflowManager->actionReceived($actionId); // ← Manager decides to ignore or process
    }
}
```

**Key principle**: WorkflowManager makes all decisions. State Handlers just notify.

## Installation

```bash
composer require duyler/workflow:dev-main
```

## Configuration

```php
// config/workflow.php

use Duyler\Workflow\WorkflowConfig;

return [
    WorkflowConfig::class => [
        'workflow_path' => $config->path('workflow'),
        'storage' => 'database', // 'memory', 'database', 'redis'
    ],
];
```

## Usage

### Define Workflow

```php
// workflow/my_workflow.php

return Workflow::define('MyWorkflow')
    ->route(HttpMethod::Post, '/start')
    ->sequence(
        Step::withId('step1')->actions([...]),
        Step::withId('step2')->actions([...]),
    );
```

### Start Workflow

```php
// Automatically started by trigger (HTTP route)
// POST /start

// Or manually
$manager->start('MyWorkflow', ['data' => 'value']);
```

### Check Status

```php
$state = $manager->getState($instanceId);
echo $state->status->value; // "running", "completed", "failed"
echo $state->currentStepId; // current step
```

## Comparison with Scenario

| Aspect | Scenario | Workflow |
|--------|----------|----------|
| Duration | Seconds | Days/weeks |
| Mode | Mode::Queue | Mode::Loop |
| Format | YAML | PHP DSL |
| Persistence | ❌ | ✅ |
| Delays | ❌ | ✅ |
| Timeouts | ❌ | ✅ |
| Retry | ❌ | ✅ |
| Compensations | ❌ | ✅ |
| Use case | HTTP request | Long-running worker |


## Roadmap

### MVP (v0.1.0) - ✅ Completed
-  DSL Layer (Workflow, Step)
-  Build Layer (WorkflowBuilder, WorkflowValidator)
-  **Contracts Layer** (ExecutorInterface, StorageInterface, ClockInterface, ActionResolverInterface)
-  Runtime Layer (WorkflowManager with tick() and actionReceived())
-  State Management (WorkflowState, WorkflowStatus)
-  **Serialization Layer** (JSON export/import, WorkflowSerializer, WorkflowDeserializer)
-  **Expression Language** (Symfony ExpressionLanguage for conditions)
-  Delays and timeouts
-  Retry with backoff (Fixed, Linear, Exponential)
-  Conditional transitions (when)
-  Parallel execution
-  Integration helpers (WorkflowLoader)

### v1.0.0 - Framework Integration (Next)
- [ ] Framework-specific StateHandlers
- [ ] Database storage implementation
- [ ] Redis storage implementation
- [ ] Event triggers integration
- [ ] HTTP API endpoints for visualization
- [ ] Real-world production testing
- [ ] Performance optimization
- [ ] Documentation for end users

### v2.0.0 - Advanced Features
- [ ] Sub-workflows (nested workflows)
- [ ] Workflow versioning
- [ ] Multiple triggers per workflow
- [ ] Dashboard integration
- [ ] Management API
- [ ] **Visual workflow builder** (drag & drop UI)
- [ ] **Workflow templates** (pre-built processes)
- [ ] Workflow analytics and metrics

## Examples

### Simple Sequential Flow

```php
return Workflow::define('SimpleFlow')
    ->sequence(
        Step::withId('step1')->actions([Action1::class]),
        Step::withId('step2')->actions([Action2::class])->isFinal(),
    );
```

### Parallel Execution

```php
Step::withId('notify_all')
    ->parallel([Email::Send, SMS::Send, Push::Send])
    ->isFinal()
```

### Conditional Routing

```php
Step::withId('check_amount')
    ->actions([Payment::Verify])
    ->when(fn($r) => $r->amount > 10000, 'manual_review')
    ->onSuccess('auto_approve')
```

### Saga Pattern

```php
Step::withId('reserve')
    ->actions([Inventory::Reserve])
    ->compensate([Inventory::Release])
    
Step::withId('charge')
    ->actions([Payment::Charge])
    ->compensate([Payment::Refund])
```

### Retry with Backoff

```php
Step::withId('api_call')
    ->actions([ExternalAPI::Call])
    ->retry(maxAttempts: 5, delaySeconds: 1, backoff: 'exponential')
    // 1s → 2s → 4s → 8s → 16s
```

## Testing

```bash
# Run all checks (tests, static analysis, code style)
composer check

# Run tests only
composer test

# Run static analysis
composer analyse

# Fix code style
composer cs-fix
```

## Requirements

- PHP 8.4+
- duyler/builder
- symfony/expression-language ^7.0

## Contributing

Core functionality is implemented and tested. The package is ready for framework integration.

## License

MIT

## Links

- [Duyler Framework](https://github.com/duyler)
- [Documentation](https://duyler.org)
- [Support](https://duyler.org/en/docs/workflow/)

