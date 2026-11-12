<?php

namespace Tests\Unit;

use App\Services\RepairWorkflow;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    public function test_technician_can_accept_and_customer_can_confirm_completion(): void
    {
        $flow = new RepairWorkflow;
        $flow->ensure('pending', 'accepted', 'technician');
        $flow->ensure('ready_for_pickup', 'completed', 'customer');
        $this->addToAssertionCount(2);
    }

    public function test_customer_cannot_accept_their_own_request(): void
    {
        $this->expectException(ValidationException::class);
        (new RepairWorkflow)->ensure('pending', 'accepted', 'customer');
    }

    public function test_technician_cannot_skip_estimate_approval(): void
    {
        $this->expectException(ValidationException::class);
        (new RepairWorkflow)->ensure('diagnosing', 'in_repair', 'technician');
    }

    public function test_completed_repairs_are_terminal(): void
    {
        $this->expectException(ValidationException::class);
        (new RepairWorkflow)->ensure('completed', 'in_repair', 'technician');
    }

    public function test_admin_cannot_use_unrestricted_workflow_transitions(): void
    {
        $this->expectException(ValidationException::class);
        (new RepairWorkflow)->ensure('pending', 'completed', 'admin');
    }
}
