<?php

return [
    'aliases' => [
        'pages' => [
            'superadminmanajemenperanpage' => 'superAdminManajemenPeranPage',
            'systemauthmanagerpage' => 'systemAuthManagerPage',
            'system-manager' => 'systemAuthManagerPage',
            'role-manager' => 'superAdminManajemenPeranPage',
        ],
        'resources' => [
            'system-manager' => 'systemAuthManagerPage',
            'role-manager' => 'superAdminManajemenPeranPage',
        ],
        'actions' => [
            'view' => 'VIEW',
            'create' => 'CREATE',
            'edit' => 'EDIT',
            'update' => 'UPDATE',
            'delete' => 'DELETE',
            'assign' => 'ASSIGN',
            'delegate' => 'DELEGATE',
            'approve' => 'APPROVE',
        ],
    ],

    'resource_access' => [
        'default_context' => 'admin',
        'default_page' => 'systemAuthManagerPage',
        'default_tab_codes' => ['dashboard', 'users', 'master', 'governance', 'distribution', 'matrix', 'monitoring'],
    ],

    'resource_registry' => [
        'definitions' => [
            'systemAuthManagerPage' => [
                'tab' => [
                    'dashboard' => 'Dashboard',
                    'users' => 'User & Roles',
                    'master' => 'Master Data',
                    'governance' => 'Module Governance',
                    'monitoring' => 'Monitoring',
                    'distribution' => 'Access Distribution',
                    'matrix' => 'Permission Matrix',
                ],
            ],
            'menuApprovalMatrixPage' => [
                'tab' => [
                    'menu' => 'Tab Menu',
                    'approval-matrix' => 'Tab Approval Matrix',
                    'membership' => 'Tab Keanggotaan',
                ],
                'capability' => [
                    'MENU_ACCESS.ASSIGN' => 'Assign Menu',
                    'APPROVAL_MATRIX.ASSIGN' => 'Assign Approval',
                    'MEMBERSHIP.ASSIGN' => 'Assign Keanggotaan',
                    'MEMBERSHIP.CONFIGURE' => 'Konfigurasi Keanggotaan',
                ],
            ],
        ],
    ],

    'cache' => [
        'cross_request_enabled' => true,
        'ttl_seconds' => 60,
    ],

    'audit' => [
        'enabled' => true,
        'db_enabled' => false,
        'channel' => 'stack',
    ],
];
