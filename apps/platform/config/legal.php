<?php

return [
    'capture_request_metadata' => env('LEGAL_CAPTURE_REQUEST_METADATA', true),

    'documents' => [
        'terms-of-service' => [
            'title' => 'Terms of Service',
            'description' => 'The commercial and usage terms for MarketLens Platform accounts.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => true,
            'summary' => 'Account owners must use MarketLens lawfully, keep credentials safe, and respect product boundaries.',
            'body' => <<<'TEXT'
# Terms of Service

These Terms of Service govern access to MarketLens public pages, Platform accounts, Monitoring App access, subscriptions, reports, synchronization monitoring, and related support services.

MarketLens is a cloud Platform for business administration and monitoring. Shop App operational writes remain controlled by the Shop App. Users must keep account credentials secure, provide accurate business and billing information, and avoid attempts to bypass tenant, branch, subscription, synchronization, device, or authorization controls.

Subscriptions, support, security, acceptable use, data retention, and privacy obligations are supplemented by the related legal documents published in this legal centre. Continued authenticated use after a material effective version requires acceptance by the user.
TEXT,
        ],
        'privacy-policy' => [
            'title' => 'Privacy Policy',
            'description' => 'How MarketLens handles account, business, device, support, and monitoring data.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => true,
            'summary' => 'MarketLens uses personal and business data to operate accounts, security, synchronization, support, billing, and monitoring features.',
            'body' => <<<'TEXT'
# Privacy Policy

MarketLens processes account profile data, business details, subscription and billing records, device information, synchronization metadata, support tickets, audit events, and monitoring usage data to provide the Platform and related services.

Where permitted and necessary for security, compliance, and abuse prevention, MarketLens may record acceptance timestamps, IP addresses, and user agents for legal-document acceptance. Private credentials, production secrets, and sensitive operational authentication material should never be submitted through support tickets.

MarketLens applies tenant and branch isolation to business records. Client Apps must not bypass the Shop App local API for operational writes, and Monitoring App visibility depends on synchronized Platform data only.
TEXT,
        ],
        'data-processing-information' => [
            'title' => 'Data Processing Information',
            'description' => 'Operational information about the data MarketLens processes for business customers.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => true,
            'summary' => 'MarketLens processes synchronized business data, account data, device data, and support data to provide the service.',
            'body' => <<<'TEXT'
# Data Processing Information

MarketLens receives synchronized records from Shop Apps for cloud reporting, subscription management, device management, alerts, and Monitoring App APIs. The Platform does not directly operate shop sales and does not make Client Apps the source of truth for shop operations.

Processing activities include authentication, account administration, subscription enforcement, synchronization intake, reporting, notification delivery, support investigation, audit logging, export generation, and retention-policy administration.

Business owners remain responsible for configuring user access, branch access, device activation, and appropriate submission of support attachments.
TEXT,
        ],
        'acceptable-use-policy' => [
            'title' => 'Acceptable Use Policy',
            'description' => 'Rules for safe and lawful use of MarketLens systems.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => true,
            'summary' => 'Users must not abuse, attack, scrape, bypass limits, or submit unlawful content through MarketLens.',
            'body' => <<<'TEXT'
# Acceptable Use Policy

Users must not use MarketLens to attack systems, probe vulnerabilities without authorization, bypass subscription or access limits, submit malicious files, upload unlawful content, or interfere with other tenants.

Users must not attempt to access another business, branch, device, synchronization batch, support ticket, report export, or Monitoring App data without permission. Device activation and report exports must respect plan limits and security rules.
TEXT,
        ],
        'subscription-and-billing-policy' => [
            'title' => 'Subscription and Billing Policy',
            'description' => 'Plan, usage, billing, downgrade, cancellation, and grace-period information.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => true,
            'summary' => 'Subscription status controls premium features, plan limits, and safe billing access.',
            'body' => <<<'TEXT'
# Subscription and Billing Policy

MarketLens plans may limit branches, devices, monitoring users, storage, reports, exports, and premium features. Device activation must respect active plan limits. Downgrades may require warnings when current usage exceeds target plan limits.

Expired or past-due subscriptions may restrict premium features after the configured grace period, while preserving safe account and billing access. Expiration does not immediately delete synchronized business records.
TEXT,
        ],
        'support-policy' => [
            'title' => 'Support Policy',
            'description' => 'How support tickets, attachments, priorities, and conversations are handled.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => false,
            'summary' => 'Support tickets should include safe context, validated attachments, and relevant branch/device/sync references.',
            'body' => <<<'TEXT'
# Support Policy

Authenticated users can submit support tickets with categories, priorities, conversations, related branch, related Shop App device, related synchronization batch, and validated attachments.

Support attachments are validated by file type and size and stored privately. Do not submit secrets, passwords, payment card data, private keys, raw production credentials, or sensitive authentication payloads through support tickets.
TEXT,
        ],
        'data-retention-policy' => [
            'title' => 'Data Retention Policy',
            'description' => 'How MarketLens retains synchronized, support, billing, and account records.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => true,
            'summary' => 'Synchronized data is preserved according to configured retention rules and is not immediately deleted when subscriptions expire.',
            'body' => <<<'TEXT'
# Data Retention Policy

MarketLens preserves synchronized business records according to configured retention policy, legal requirements, operational needs, backup constraints, and account status. Subscription expiration or cancellation request does not immediately delete synchronized history.

Business data exports, audit history, support tickets, legal acceptance records, subscription records, and security logs may be retained where needed for compliance, fraud prevention, dispute resolution, service operation, and security investigation.
TEXT,
        ],
        'security-overview' => [
            'title' => 'Security Overview',
            'description' => 'High-level security practices for Platform accounts, tenant isolation, devices, and synchronized data.',
            'version' => '2026.07.31',
            'published_at' => '2026-07-31 00:00:00',
            'effective_at' => '2026-08-01 00:00:00',
            'material' => false,
            'summary' => 'MarketLens uses account controls, tenant isolation, signed links, hashed device credentials, audit logs, and private support attachments.',
            'body' => <<<'TEXT'
# Security Overview

MarketLens protects Platform access with authenticated accounts, verified email, active account checks, tenant-scoped business context, branch-aware authorization, audit logging, hashed device credentials, signed export links, and private support attachments.

The Platform receives synchronized data from authorized Shop App devices. Monitoring App users access synchronized Platform data only and must not connect directly to a Shop App. Business owners are responsible for assigning appropriate roles, branch access, and module access.
TEXT,
        ],
    ],
];
