import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../widgets/glass_card.dart';
import '../widgets/page_scaffold.dart';

/// Referral / invite friends (Phase 4) — share customer code or pay link.
class CustomerReferralScreen extends StatelessWidget {
  const CustomerReferralScreen({super.key, required this.api, this.customerCode, this.customerName});

  final ApiService api;
  final String? customerCode;
  final String? customerName;

  Future<void> _share(BuildContext context) async {
    final app = RemoteConfig.appName;
    final pay = RemoteConfig.payUrl;
    final code = customerCode?.trim();
    final msg = StringBuffer('Join $app for fast home internet!\n');
    if (code != null && code.isNotEmpty) {
      msg.writeln('Use my referral code: $code');
    }
    if (pay != null && pay.isNotEmpty) {
      msg.writeln('Pay bill: $pay');
    }
    await Share.share(msg.toString(), subject: '$app referral');
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Refer a friend',
      useGradientBody: true,
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          GlassCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Invite friends & earn rewards', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Text(
                  customerName != null ? 'Hi $customerName — share your code with neighbors.' : 'Share your connection details with friends.',
                  style: TextStyle(color: Colors.grey.shade600),
                ),
                if (customerCode != null && customerCode!.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  SelectableText(customerCode!, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: 2)),
                  const SizedBox(height: 4),
                  const Text('Your referral code', style: TextStyle(fontSize: 12)),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: () => _share(context),
            icon: const Icon(Icons.share),
            label: const Text('Share invite'),
          ),
        ],
      ),
    );
  }
}
