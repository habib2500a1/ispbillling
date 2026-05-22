import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../screens/staff_receive_bill_screen.dart';
import '../utils/app_nav.dart';
import 'isp_ui_kit.dart';

/// ISP-style billing list row (due clients) with Pay, SMS, extend, network toggle.
class BillingClientCard extends StatelessWidget {
  const BillingClientCard({
    super.key,
    required this.api,
    required this.client,
    required this.onChanged,
  });

  final ApiService api;
  final Map<String, dynamic> client;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.0');
    final due = (client['balance_due'] as num?)?.toDouble() ?? 0;
    final monthly = (client['monthly_bill'] as num?)?.toDouble()
        ?? (client['monthly_payable'] as num?)?.toDouble()
        ?? 0;
    final networkOn = client['network_on'] != false;
    final expireDay = client['expire_day']?.toString() ?? '—';
    final zone = client['zone']?.toString() ?? '';
    final address = client['address']?.toString() ?? '';
    final id = (client['id'] as num).toInt();

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        client['name']?.toString() ?? '',
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${client['customer_code']} · ${client['username'] ?? client['phone'] ?? ''}',
                        style: const TextStyle(fontSize: 11, color: Colors.black54),
                      ),
                      if (zone.isNotEmpty)
                        Text(zone, style: const TextStyle(fontSize: 11, color: Colors.deepOrange, fontWeight: FontWeight.w600)),
                      if (address.isNotEmpty)
                        Text(address, style: const TextStyle(fontSize: 11, color: Colors.deepOrange)),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      fmt.format(due),
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.warning),
                    ),
                    const Text('Due', style: TextStyle(fontSize: 10, color: Colors.black45)),
                    const SizedBox(height: 6),
                    IspUiKit.payButton(onPressed: () => _openFullReceiveBill(context)),
                  ],
                ),
              ],
            ),
          ),
          IspUiKit.clientFooterBar(
            children: [
                IconButton(
                  tooltip: 'Extend 30 days',
                  icon: const Icon(Icons.refresh, size: 20, color: AppTheme.primary),
                  onPressed: () => _extend(context, id),
                  visualDensity: VisualDensity.compact,
                ),
                Text('Ex. $expireDay', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    client['package']?.toString() ?? '',
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade700),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Text('M ${fmt.format(monthly)}', style: const TextStyle(fontSize: 11)),
                const SizedBox(width: 8),
                const Text('Net', style: TextStyle(fontSize: 11)),
                Switch.adaptive(
                  value: networkOn,
                  activeColor: AppTheme.primary,
                  onChanged: (_) => _toggleNetwork(context, id),
                ),
                IconButton(
                  icon: const Icon(Icons.sms, color: AppTheme.success, size: 22),
                  tooltip: 'SMS reminder',
                  onPressed: () => _sms(context, id),
                  visualDensity: VisualDensity.compact,
                ),
                IconButton(
                  icon: const Icon(Icons.receipt_long, size: 22),
                  tooltip: 'Receive bill',
                  onPressed: () => _openFullReceiveBill(context),
                  visualDensity: VisualDensity.compact,
                ),
              ],
          ),
        ],
      ),
    );
  }

  Future<void> _openFullReceiveBill(BuildContext context) async {
    final id = (client['id'] as num).toInt();
    Map<String, dynamic> detail;
    try {
      detail = await api.staffCustomerDetail(id);
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, isError: true);
      return;
    }
    if (!context.mounted) return;
    final ok = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => StaffReceiveBillScreen(api: api, customer: detail),
      ),
    );
    if (ok == true) onChanged();
  }

  Future<void> _extend(BuildContext context, int id) async {
    try {
      await api.staffExtendService(id);
      if (context.mounted) showSnack(context, 'Service extended 30 days');
      onChanged();
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, isError: true);
    }
  }

  Future<void> _toggleNetwork(BuildContext context, int id) async {
    try {
      await api.staffToggleNetwork(id);
      onChanged();
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, isError: true);
    }
  }

  Future<void> _sms(BuildContext context, int id) async {
    try {
      await api.staffSmsReminder(id);
      if (context.mounted) showSnack(context, 'SMS sent');
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, isError: true);
    }
  }
}
