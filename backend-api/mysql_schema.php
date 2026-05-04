<?php

declare(strict_types=1);

function mysql_schema_queries(): array
{
    return [
        'CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            account_type VARCHAR(32) NOT NULL,
            username VARCHAR(191) NOT NULL UNIQUE,
            email VARCHAR(191) NULL,
            mobile VARCHAR(64) NULL,
            country_code VARCHAR(16) NULL,
            mobile_e164 VARCHAR(64) NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "normal",
            lang VARCHAR(32) NOT NULL DEFAULT "eng",
            avatar_id VARCHAR(64) NULL,
            invitation_code VARCHAR(191) NOT NULL UNIQUE,
            invited_by_user_id BIGINT UNSIGNED NULL,
            invited_by_admin_id BIGINT UNSIGNED NULL,
            admin_group_code VARCHAR(64) NULL,
            login_failure_count INT NOT NULL DEFAULT 0,
            last_login_at VARCHAR(64) NULL,
            last_login_ip VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_users_group (admin_group_code, id),
            KEY idx_users_invited_admin (invited_by_admin_id, id),
            KEY idx_users_invited_user (invited_by_user_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS invitation_codes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(191) NOT NULL UNIQUE,
            status VARCHAR(32) NOT NULL DEFAULT "active",
            is_primary TINYINT(1) NOT NULL DEFAULT 1,
            issued_at VARCHAR(64) NOT NULL,
            expires_at VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS verification_codes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(32) NOT NULL,
            target VARCHAR(191) NOT NULL,
            event VARCHAR(64) NOT NULL,
            code VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            attempt_count INT NOT NULL DEFAULT 0,
            sent_ip VARCHAR(64) NULL,
            sent_at VARCHAR(64) NOT NULL,
            expires_at VARCHAR(64) NOT NULL,
            consumed_at VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS user_wallet_balances (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            wallet_code VARCHAR(64) NOT NULL,
            currency_code VARCHAR(32) NOT NULL,
            available_balance VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            reserved_balance VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            UNIQUE KEY uniq_user_wallet (user_id, wallet_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS financial_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_code VARCHAR(191) NOT NULL UNIQUE,
            admin_group_code VARCHAR(64) NULL,
            asset_code VARCHAR(32) NOT NULL,
            wallet_code VARCHAR(64) NOT NULL,
            display_name VARCHAR(191) NULL,
            subtitle VARCHAR(255) NULL,
            detail_note TEXT NULL,
            apr_rate VARCHAR(64) NOT NULL,
            term_days INT NOT NULL,
            min_subscribe_amount VARCHAR(64) NOT NULL,
            personal_limit_amount VARCHAR(64) NOT NULL,
            total_quota_amount VARCHAR(64) NOT NULL,
            sold_quota_amount VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            auto_renew_default TINYINT(1) NOT NULL DEFAULT 0,
            default_return_mode VARCHAR(32) NOT NULL DEFAULT "auto",
            default_return_delay_days INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT "active",
            sort_order INT NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_financial_products_group (admin_group_code, status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS user_financial_subscriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            product_code VARCHAR(191) NOT NULL,
            asset_code VARCHAR(32) NOT NULL,
            wallet_code VARCHAR(64) NOT NULL,
            amount VARCHAR(64) NOT NULL,
            apr_rate VARCHAR(64) NOT NULL,
            term_days INT NOT NULL,
            estimated_interest VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            status VARCHAR(32) NOT NULL DEFAULT "active",
            return_mode VARCHAR(32) NOT NULL DEFAULT "manual",
            return_delay_days INT NOT NULL DEFAULT 0,
            subscribed_at VARCHAR(64) NOT NULL,
            interest_start_at VARCHAR(64) NOT NULL,
            maturity_at VARCHAR(64) NOT NULL,
            return_scheduled_at VARCHAR(64) NULL,
            settled_at VARCHAR(64) NULL,
            returned_by_admin_id BIGINT UNSIGNED NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS user_tier_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            level INT NOT NULL DEFAULT 0,
            group_code VARCHAR(191) NULL,
            score INT NOT NULL DEFAULT 30,
            merchant_enabled TINYINT(1) NOT NULL DEFAULT 0,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            daily_trade_limit INT NULL,
            min_sell_amount VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            margin_amount VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            margin_ratio VARCHAR(64) NOT NULL DEFAULT "0.0000",
            risk_status VARCHAR(32) NOT NULL DEFAULT "normal",
            violation_message TEXT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS system_configs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            config_group VARCHAR(64) NOT NULL,
            config_key VARCHAR(191) NOT NULL,
            config_value LONGTEXT NOT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            UNIQUE KEY uniq_system_config (config_group, config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS deposit_addresses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            asset_code VARCHAR(32) NOT NULL,
            network_code VARCHAR(64) NOT NULL,
            network_label VARCHAR(64) NOT NULL,
            address VARCHAR(255) NOT NULL,
            qr_code_url TEXT NULL,
            remark TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "enabled",
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            UNIQUE KEY uniq_deposit_address (asset_code, network_code, address),
            KEY idx_deposit_addresses_user_asset (user_id, asset_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS deposit_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            amount VARCHAR(64) NOT NULL,
            asset_code VARCHAR(32) NOT NULL,
            network VARCHAR(64) NULL,
            target_wallet_code VARCHAR(64) NOT NULL,
            proof_url TEXT NULL,
            reference_text TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "pending",
            admin_note TEXT NULL,
            reviewed_by_admin_id BIGINT UNSIGNED NULL,
            reviewed_at VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_deposit_requests_user (user_id, id),
            KEY idx_deposit_requests_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS withdrawal_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            amount VARCHAR(64) NOT NULL,
            asset_code VARCHAR(32) NOT NULL,
            channel_type VARCHAR(32) NOT NULL,
            payout_method_id BIGINT UNSIGNED NULL,
            payout_address TEXT NULL,
            source_wallet_code VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "pending",
            admin_note TEXT NULL,
            reviewed_by_admin_id BIGINT UNSIGNED NULL,
            reviewed_at VARCHAR(64) NULL,
            remark TEXT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_withdrawal_requests_user (user_id, id),
            KEY idx_withdrawal_requests_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS c2c_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_no VARCHAR(191) NOT NULL UNIQUE,
            listing_id BIGINT UNSIGNED NULL,
            admin_group_code VARCHAR(64) NULL,
            side VARCHAR(32) NOT NULL,
            buyer_user_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NOT NULL,
            amount VARCHAR(64) NOT NULL,
            price VARCHAR(64) NOT NULL,
            total_amount VARCHAR(64) NOT NULL,
            asset_code VARCHAR(32) NOT NULL,
            fiat_code VARCHAR(32) NOT NULL,
            payment_method_summary TEXT NULL,
            status VARCHAR(32) NOT NULL,
            completed_at VARCHAR(64) NULL,
            cancel_reason TEXT NULL,
            dispute_reason TEXT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_c2c_orders_group (admin_group_code, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS order_evidences (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            actor_type VARCHAR(32) NOT NULL,
            actor_id BIGINT UNSIGNED NULL,
            evidence_type VARCHAR(64) NOT NULL,
            content TEXT NULL,
            attachment_url TEXT NULL,
            created_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS c2c_listings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            admin_group_code VARCHAR(64) NULL,
            nickname VARCHAR(191) NOT NULL,
            side VARCHAR(32) NOT NULL,
            asset_code VARCHAR(32) NOT NULL,
            fiat_code VARCHAR(32) NOT NULL,
            price VARCHAR(64) NOT NULL,
            min_amount VARCHAR(64) NOT NULL,
            max_amount VARCHAR(64) NOT NULL,
            available_amount VARCHAR(64) NOT NULL,
            payment_method_summary TEXT NULL,
            completion_rate VARCHAR(64) NULL,
            badge_vip TINYINT(1) NOT NULL DEFAULT 0,
            badge_pro TINYINT(1) NOT NULL DEFAULT 0,
            badge_stars TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT "active",
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_c2c_listings_group (admin_group_code, status, side)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS trade_feed_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_group_code VARCHAR(64) NULL,
            action_type VARCHAR(64) NOT NULL DEFAULT "custom",
            title VARCHAR(255) NOT NULL,
            actor_name VARCHAR(191) NOT NULL,
            asset_code VARCHAR(32) NOT NULL,
            amount VARCHAR(64) NOT NULL,
            occurred_at VARCHAR(64) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT "active",
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_trade_feed_group (admin_group_code, status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS user_kyc_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            legal_name VARCHAR(191) NULL,
            id_number_masked VARCHAR(191) NULL,
            id_doc_front_url TEXT NULL,
            id_doc_back_url TEXT NULL,
            selfie_url TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "pending",
            review_note TEXT NULL,
            reviewed_by_admin_id BIGINT UNSIGNED NULL,
            reviewed_at VARCHAR(64) NULL,
            submitted_at VARCHAR(64) NOT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_kyc_applications_user (user_id, id),
            KEY idx_kyc_applications_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS user_payout_methods (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            channel_type VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "pending",
            bank_name VARCHAR(191) NULL,
            account_holder VARCHAR(191) NULL,
            account_no_masked VARCHAR(191) NULL,
            usdt_network VARCHAR(64) NULL,
            payout_address TEXT NULL,
            pix_key VARCHAR(191) NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            review_note TEXT NULL,
            reviewed_by_admin_id BIGINT UNSIGNED NULL,
            reviewed_at VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_payout_methods_user (user_id, id),
            KEY idx_payout_methods_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS admin_users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "normal",
            login_failure_count INT NOT NULL DEFAULT 0,
            login_first_failure_at VARCHAR(64) NULL,
            login_last_failure_at VARCHAR(64) NULL,
            login_locked_until VARCHAR(64) NULL,
            login_lock_level INT NOT NULL DEFAULT 0,
            login_permanent_locked_at VARCHAR(64) NULL,
            role_codes LONGTEXT NOT NULL,
            permissions LONGTEXT NOT NULL,
            display_name VARCHAR(191) NULL,
            staff_invite_code VARCHAR(191) NULL,
            admin_group_code VARCHAR(64) NULL,
            admin_group_name VARCHAR(191) NULL,
            can_view_group_global_data TINYINT(1) NOT NULL DEFAULT 0,
            created_by_admin_id BIGINT UNSIGNED NULL,
            parent_admin_id BIGINT UNSIGNED NULL,
            password_must_change TINYINT(1) NOT NULL DEFAULT 1,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS admin_role_templates (
            template_key VARCHAR(64) NOT NULL PRIMARY KEY,
            label VARCHAR(191) NOT NULL,
            module_access_json LONGTEXT NOT NULL,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS admin_groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            group_name VARCHAR(191) NOT NULL,
            group_code VARCHAR(64) NOT NULL UNIQUE,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS admin_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(191) NOT NULL UNIQUE,
            issued_at VARCHAR(64) NOT NULL,
            expires_at VARCHAR(64) NOT NULL,
            revoked_at VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS admin_login_rate_limits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(191) NOT NULL UNIQUE,
            account VARCHAR(191) NULL,
            ip VARCHAR(64) NOT NULL,
            failure_count INT NOT NULL DEFAULT 0,
            first_failure_at VARCHAR(64) NULL,
            last_attempt_at VARCHAR(64) NULL,
            blocked_until VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS user_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(191) NOT NULL UNIQUE,
            issued_at VARCHAR(64) NOT NULL,
            expires_at VARCHAR(64) NOT NULL,
            revoked_at VARCHAR(64) NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS wallet_ledger (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            wallet_id BIGINT UNSIGNED NULL,
            wallet_code VARCHAR(64) NOT NULL,
            currency_code VARCHAR(32) NOT NULL,
            change_type VARCHAR(64) NOT NULL,
            source_type VARCHAR(64) NOT NULL,
            source_id BIGINT UNSIGNED NULL,
            available_delta VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            reserved_delta VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            available_before VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            reserved_before VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            available_after VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            reserved_after VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            operator_type VARCHAR(32) NULL,
            operator_id BIGINT UNSIGNED NULL,
            reason TEXT NULL,
            metadata_json LONGTEXT NULL,
            created_at VARCHAR(64) NOT NULL,
            KEY idx_wallet_ledger_user (user_id, id),
            KEY idx_wallet_ledger_wallet (wallet_code, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS tier_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            template_code VARCHAR(191) NOT NULL UNIQUE,
            display_name VARCHAR(191) NOT NULL,
            level INT NOT NULL DEFAULT 1,
            group_code VARCHAR(191) NOT NULL,
            score INT NOT NULL DEFAULT 0,
            merchant_enabled TINYINT(1) NOT NULL DEFAULT 0,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            daily_trade_limit INT NULL,
            min_sell_amount VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            margin_amount VARCHAR(64) NOT NULL DEFAULT "0.00000000",
            margin_ratio VARCHAR(64) NOT NULL DEFAULT "0.0000",
            risk_status VARCHAR(32) NOT NULL DEFAULT "normal",
            description TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "active",
            sort_order INT NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS support_tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            category VARCHAR(64) NOT NULL DEFAULT "general",
            subject VARCHAR(191) NOT NULL,
            content TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT "open",
            priority VARCHAR(32) NOT NULL DEFAULT "normal",
            assigned_admin_id BIGINT UNSIGNED NULL,
            admin_note TEXT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL,
            KEY idx_support_tickets_user (user_id, id),
            KEY idx_support_tickets_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        'CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(64) NOT NULL,
            action VARCHAR(128) NOT NULL,
            operator_type VARCHAR(32) NULL,
            operator_id BIGINT UNSIGNED NULL,
            target_type VARCHAR(32) NULL,
            target_id BIGINT UNSIGNED NULL,
            reason TEXT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            error_code VARCHAR(128) NULL,
            payload_json LONGTEXT NULL,
            ip VARCHAR(64) NULL,
            account VARCHAR(191) NULL,
            msg TEXT NULL,
            created_at VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    ];
}

function mysql_schema_missing_columns(): array
{
    return [
        'audit_logs' => [
            'ip' => 'VARCHAR(64) NULL',
            'account' => 'VARCHAR(191) NULL',
            'msg' => 'TEXT NULL',
        ],
        'admin_users' => [
            'login_failure_count' => 'INT NOT NULL DEFAULT 0',
            'login_first_failure_at' => 'VARCHAR(64) NULL',
            'login_last_failure_at' => 'VARCHAR(64) NULL',
            'login_locked_until' => 'VARCHAR(64) NULL',
            'login_lock_level' => 'INT NOT NULL DEFAULT 0',
            'login_permanent_locked_at' => 'VARCHAR(64) NULL',
            'display_name' => 'VARCHAR(191) NULL',
            'staff_invite_code' => 'VARCHAR(191) NULL',
            'admin_group_code' => 'VARCHAR(64) NULL',
            'admin_group_name' => 'VARCHAR(191) NULL',
            'can_view_group_global_data' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_by_admin_id' => 'BIGINT UNSIGNED NULL',
            'parent_admin_id' => 'BIGINT UNSIGNED NULL',
            'password_must_change' => 'TINYINT(1) NOT NULL DEFAULT 1',
        ],
        'admin_role_templates' => [
            'created_by_admin_id' => 'BIGINT UNSIGNED NULL',
        ],
        'admin_groups' => [
            'created_by_admin_id' => 'BIGINT UNSIGNED NULL',
        ],
        'users' => [
            'invited_by_admin_id' => 'BIGINT UNSIGNED NULL',
            'admin_group_code' => 'VARCHAR(64) NULL',
            'avatar_id' => 'VARCHAR(64) NULL',
        ],
        'deposit_addresses' => [
            'user_id' => 'BIGINT UNSIGNED NULL',
        ],
        'withdrawal_requests' => [
            'remark' => 'TEXT NULL',
        ],
        'financial_products' => [
            'admin_group_code' => 'VARCHAR(64) NULL',
            'display_name' => 'VARCHAR(191) NULL',
            'subtitle' => 'VARCHAR(255) NULL',
            'detail_note' => 'TEXT NULL',
            'default_return_mode' => 'VARCHAR(32) NOT NULL DEFAULT "auto"',
            'default_return_delay_days' => 'INT NOT NULL DEFAULT 0',
        ],
        'user_financial_subscriptions' => [
            'return_mode' => 'VARCHAR(32) NOT NULL DEFAULT "manual"',
            'return_delay_days' => 'INT NOT NULL DEFAULT 0',
            'return_scheduled_at' => 'VARCHAR(64) NULL',
            'returned_by_admin_id' => 'BIGINT UNSIGNED NULL',
        ],
        'trade_feed_events' => [
            'admin_group_code' => 'VARCHAR(64) NULL',
            'action_type' => 'VARCHAR(64) NOT NULL DEFAULT "custom"',
        ],
        'c2c_listings' => [
            'admin_group_code' => 'VARCHAR(64) NULL',
            'badge_vip' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'badge_pro' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'badge_stars' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ],
        'c2c_orders' => [
            'listing_id' => 'BIGINT UNSIGNED NULL',
            'admin_group_code' => 'VARCHAR(64) NULL',
        ],
    ];
}

function mysql_schema_missing_indexes(): array
{
    return [
        'users' => [
            'idx_users_group' => '(admin_group_code, id)',
            'idx_users_invited_admin' => '(invited_by_admin_id, id)',
            'idx_users_invited_user' => '(invited_by_user_id, id)',
        ],
        'deposit_addresses' => [
            'idx_deposit_addresses_user_asset' => '(user_id, asset_code)',
        ],
        'deposit_requests' => [
            'idx_deposit_requests_user' => '(user_id, id)',
            'idx_deposit_requests_status' => '(status, id)',
        ],
        'withdrawal_requests' => [
            'idx_withdrawal_requests_user' => '(user_id, id)',
            'idx_withdrawal_requests_status' => '(status, id)',
        ],
        'user_kyc_applications' => [
            'idx_kyc_applications_user' => '(user_id, id)',
            'idx_kyc_applications_status' => '(status, id)',
        ],
        'user_payout_methods' => [
            'idx_payout_methods_user' => '(user_id, id)',
            'idx_payout_methods_status' => '(status, id)',
        ],
        'support_tickets' => [
            'idx_support_tickets_user' => '(user_id, id)',
            'idx_support_tickets_status' => '(status, id)',
        ],
    ];
}
