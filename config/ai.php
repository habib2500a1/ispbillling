<?php

return [
  'enabled' => env('AI_ENABLED', true),

  'llm' => [
    'enabled' => env('AI_LLM_ENABLED', false),
    'provider' => env('AI_LLM_PROVIDER', 'openai'),
    'api_key' => env('AI_LLM_API_KEY'),
    'model' => env('AI_LLM_MODEL', 'gpt-4o-mini'),
    'base_url' => rtrim((string) env('AI_LLM_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'timeout' => (int) env('AI_LLM_TIMEOUT', 30),
  ],

  'bengali_replies' => env('AI_BENGALI_REPLIES', true),
  'mask_pii' => env('AI_MASK_PII', true),
  'audit_retention_days' => (int) env('AI_AUDIT_RETENTION_DAYS', 90),
  'daily_query_limit' => (int) env('AI_DAILY_QUERY_LIMIT', 500),

  'customer_ai_enabled' => env('AI_CUSTOMER_ENABLED', true),
  'reseller_ai_enabled' => env('AI_RESELLER_ENABLED', true),
  'proactive_digest_enabled' => env('AI_PROACTIVE_DIGEST', true),
  'proactive_digest_hour' => (int) env('AI_PROACTIVE_DIGEST_HOUR', 9),

  'rag_enabled' => env('AI_RAG_ENABLED', true),
  'actions_enabled' => env('AI_ACTIONS_ENABLED', true),

  'allowed_tools' => [
    'billing.due_customers',
    'billing.today_collection',
    'billing.monthly_revenue',
    'billing.revenue_by_zone',
    'billing.top_packages',
    'network.offline_onus',
    'network.offline_routers',
    'network.weak_signals',
    'network.olt_capacity',
    'network.rca',
    'support.open_tickets',
    'support.complaint_trends',
    'support.ticket_triage',
    'gis.complaint_density',
    'inventory.low_stock',
    'inventory.warranty_expiring',
    'hr.technician_performance',
    'hr.attendance',
    'bi.recommendations',
    'bi.churn',
    'bi.churn_scored',
    'bi.summary',
    'actions.propose_suspend_defaulters',
  ],
];
