import 'package:flutter/material.dart';

import '../design_system/radiant_tokens.dart';

/// Blue SOFTIFY-style screen header with optional toolbar row.
class LegacySoftifyScreenHeader extends StatelessWidget {
  const LegacySoftifyScreenHeader({
    super.key,
    required this.title,
    this.showBack = true,
    this.onBack,
    this.toolbar,
    this.trailing,
  });

  final String title;
  final bool showBack;
  final VoidCallback? onBack;
  final Widget? toolbar;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: RadiantTokens.brand,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(4, 4, 12, 14),
          child: Column(
            children: [
              Row(
                children: [
                  if (showBack)
                    IconButton(
                      onPressed: onBack ?? () => Navigator.maybePop(context),
                      icon: const Icon(Icons.arrow_back, color: Colors.white),
                    )
                  else
                    const SizedBox(width: 48),
                  Expanded(
                    child: Text(
                      title,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w600),
                    ),
                  ),
                  SizedBox(
                    width: 48,
                    child: trailing,
                  ),
                ],
              ),
              if (toolbar != null) ...[
                const SizedBox(height: 8),
                toolbar!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// White search field + filter button row used on legacy list screens.
class LegacySoftifySearchToolbar extends StatelessWidget {
  const LegacySoftifySearchToolbar({
    super.key,
    required this.controller,
    this.hint = 'Name/C.Code/Mobile/UserID/IP',
    this.onFilter,
    this.onClear,
    this.leading,
  });

  final TextEditingController controller;
  final String hint;
  final VoidCallback? onFilter;
  final VoidCallback? onClear;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (leading != null) ...[
          leading!,
          const SizedBox(width: 8),
        ],
        Expanded(
          child: Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            child: TextField(
              controller: controller,
              style: const TextStyle(fontSize: 14),
              decoration: InputDecoration(
                hintText: hint,
                hintStyle: TextStyle(fontSize: 13, color: Colors.grey.shade500),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                suffixIcon: controller.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.close, color: Colors.red, size: 20),
                        onPressed: onClear,
                      )
                    : null,
              ),
            ),
          ),
        ),
        const SizedBox(width: 8),
        Material(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: onFilter,
            child: const SizedBox(
              width: 44,
              height: 44,
              child: Icon(Icons.filter_list, color: RadiantTokens.brand),
            ),
          ),
        ),
      ],
    );
  }
}

/// 2x2 KPI grid for billing list summary.
class LegacyBillingStatsGrid extends StatelessWidget {
  const LegacyBillingStatsGrid({
    super.key,
    required this.paidClients,
    required this.unpaidClients,
    required this.receivedBill,
    required this.dueAmount,
    this.formatter,
  });

  final int paidClients;
  final int unpaidClients;
  final double receivedBill;
  final double dueAmount;
  final String Function(double)? formatter;

  @override
  Widget build(BuildContext context) {
    final fmt = formatter ?? (v) => v.toStringAsFixed(2);
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
      child: GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 2.2,
        children: [
          _statTile(Icons.person, 'Paid Client', '$paidClients', const Color(0xFF42A5F5)),
          _statTile(Icons.groups, 'Unpaid Client', '$unpaidClients', const Color(0xFFEC407A)),
          _statTile(Icons.verified_user, 'Recived Bill', fmt(receivedBill), const Color(0xFF66BB6A)),
          _statTile(Icons.access_time_filled, 'Due Amount', fmt(dueAmount), const Color(0xFFEF5350)),
        ],
      ),
    );
  }

  Widget _statTile(IconData icon, String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 4)],
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
