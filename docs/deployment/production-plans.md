# Production subscription plans

Production and Profit Sharing is included in the default Professional monthly and annual plans. Basic and Standard remain unchanged. Plan prices and customer subscriptions are preserved.

Super administrators can use Billing > Plans > Production and Profit Sharing to include or remove the module on any plan. This stores the module:production entitlement, not only display text. Module removal affects businesses subscribed to that plan after their next entitlement refresh.

Businesses must enable Production in business/branch settings and refresh the Business Server subscription. Local roles still control operations; only the local Owner configures staff/machine sharing. Platform monitoring permissions remain separate.

Migration 2026_09_13_000002_add_production_to_professional_plans updates existing Professional tiers idempotently. New installations receive the feature from PlanSeeder, which now preserves explicit module keys. Do not rerun the full plan seeder to apply this change to customized live plans.

The plan editor validates slug uniqueness per billing interval, so monthly and annual versions can share a tier slug. Saving uses the correct minor-unit conversion and does not multiply an existing price.
