<?php

namespace App\Common;

class Constants
{
    const PAYMENT_STATUSES = [
        'PENDING' => 'Pending',
        'SUCCESSFUL' => 'Successful',
        'FAILED' => 'Failed',
    ];
    const SETTLEMENT_STATUSES = [
        'PENDING' => 'Pending',
        'SUCCESSFUL' => 'Successful',
        'FAILED' => 'Failed',
    ];

    const STATUSES_FOR_SEARCH = [
        'ACTIVE' => 'Active',
        'INACTIVE' => 'Inactive',
        'PENDING' => 'Pending',
        'APPROVED' => 'Approved',
        'DECLINED' => 'Declined',
        'DISABLED' => 'Disabled',
        'SUSPENDED' => 'Suspended',
        'BLOCKED' => 'Blocked',
        'DELETED' => 'Deleted',
    ];

    const USER_CLASSES = [
        'ADMIN' => 'Admin',
        'MERCHANT' => 'Merchant',
        'CUSTOMER' => 'Customer',
    ];

}
