<?php

namespace App\Models;

use Orchid\Platform\Models\Role as ORole;

class Role extends ORole
{

    public const string ROLE_NAME_ADMIN = 'admin';

    public const string ROLE_NAME_MANAGER = 'manager';
    public const string ROLE_NAME_USER = 'user';

    public const string PERMISSION_TRX_WALLETS = 'platform.trx_wallets.list';

    public const string PERMISSION_SETTINGS = 'platform.settings.list';
    public const string PERMISSION_RECIPIENTS = 'platform.recipients.list';
    public const string PERMISSION_OPERATIONS = 'platform.operations.list';
    public const string PERMISSION_AML = 'platform.aml.list';
    public const string PERMISSION_OPERATIONS_SET_WALLET = 'platform.operations.set_wallet';
    public const string PERMISSION_OPERATIONS_SET_CUSTOM_WALLET = 'platform.operations.set_custom_wallet';
    public const string PERMISSION_TESTING = 'platform.testing.operation';
    public const string PERMISSION_SECURITY_BLOCKED_IPS = 'platform.security.blocked-ips.list';
    public const string PERMISSION_ENERGY_WALLET_POOL = 'platform.energy.wallet-pool';



}
