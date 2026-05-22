import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../theme/app_theme.dart';

/// Shared Radiant ISP mobile UI — admin + client screens.
class IspUiKit {
  static const Color screenBg = AppTheme.background;
  static const Color panelBg = Color(0xFFE8EEF5);
  static const Color sectionTint = Color(0xFFE3F2FD);

  static BoxDecoration cardDecoration({Color? tint}) => BoxDecoration(
        color: tint ?? AppTheme.card,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      );

  /// Admin home / collection gradient header.
  static Widget gradientHeader({
    required String title,
    String? subtitle,
    List<Widget>? trailing,
    Widget? child,
  }) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [AppTheme.primary, AppTheme.purple, AppTheme.pink],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(22),
          bottomRight: Radius.circular(22),
        ),
      ),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    if (subtitle != null)
                      Text(
                        subtitle,
                        style: const TextStyle(color: Colors.white70, fontSize: 12),
                      ),
                  ],
                ),
              ),
              if (trailing != null) ...trailing,
            ],
          ),
          if (child != null) ...[const SizedBox(height: 12), child],
        ],
      ),
    );
  }

  static Widget searchBar({
    required TextEditingController controller,
    String hint = 'Search…',
    VoidCallback? onSearch,
    bool loading = false,
    VoidCallback? onClear,
  }) {
    return Container(
      decoration: cardDecoration(),
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
      child: TextField(
        controller: controller,
        decoration: InputDecoration(
          hintText: hint,
          border: InputBorder.none,
          prefixIcon: const Icon(Icons.search, color: AppTheme.primary),
          suffixIcon: loading
              ? const Padding(
                  padding: EdgeInsets.all(12),
                  child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                )
              : (controller.text.isNotEmpty && onClear != null
                  ? IconButton(icon: const Icon(Icons.clear), onPressed: onClear)
                  : null),
        ),
        onSubmitted: onSearch != null ? (_) => onSearch() : null,
      ),
    );
  }

  /// 4-column billing summary strip (Paid / Unpaid / Received / Due).
  static Widget billingSummaryStrip({
    required String paidCount,
    required String unpaidCount,
    required String received,
    required String due,
  }) {
    return Container(
      decoration: cardDecoration(tint: sectionTint),
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
      child: Row(
        children: [
          _summaryCell('Paid', paidCount, AppTheme.success),
          _summaryCell('Unpaid', unpaidCount, AppTheme.warning),
          _summaryCell('Received', received, AppTheme.primary),
          _summaryCell('Due', due, AppTheme.danger),
        ],
      ),
    );
  }

  static Widget _summaryCell(String label, String value, Color color) {
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: color),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF64748B))),
        ],
      ),
    );
  }

  static Widget payButton({required VoidCallback onPressed, String label = 'Pay'}) {
    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(
        backgroundColor: Colors.deepOrange,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
        minimumSize: Size.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
    );
  }

  static Widget clientFooterBar({required List<Widget> children}) {
    return Container(
      color: AppTheme.primary.withValues(alpha: 0.08),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      child: Row(children: children),
    );
  }

  static Widget collectionRowCard({
    required String name,
    required String codeLine,
    required String amount,
    String? meta,
    String? dateLine,
    VoidCallback? onTap,
  }) {
    return Material(
      color: AppTheme.card,
      borderRadius: BorderRadius.circular(14),
      elevation: 1,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
                    child: Text(
                      name.isNotEmpty ? name[0].toUpperCase() : '?',
                      style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                        Text(codeLine, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      ],
                    ),
                  ),
                  Text(
                    amount,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.success),
                  ),
                ],
              ),
              if (meta != null) ...[
                const SizedBox(height: 6),
                Text(meta, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
              ],
              if (dateLine != null)
                Text(dateLine, style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
            ],
          ),
        ),
      ),
    );
  }

  static Widget packageCard({
    required String title,
    required String speedLine,
    required String price,
    required bool isCurrent,
    VoidCallback? onRequest,
  }) {
    return Container(
      decoration: cardDecoration(),
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [AppTheme.primary.withValues(alpha: 0.15), AppTheme.teal.withValues(alpha: 0.2)],
              ),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.speed, color: AppTheme.primary, size: 30),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                const SizedBox(height: 4),
                Text(speedLine, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                if (isCurrent)
                  const Text('Current plan', style: TextStyle(fontSize: 11, color: AppTheme.success, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(price, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              const SizedBox(height: 6),
              if (isCurrent)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppTheme.success.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Text('Active', style: TextStyle(fontSize: 11, color: AppTheme.success, fontWeight: FontWeight.w700)),
                )
              else if (onRequest != null)
                IconButton(
                  onPressed: onRequest,
                  icon: const Icon(Icons.swap_horiz, color: Colors.deepOrange, size: 28),
                ),
            ],
          ),
        ],
      ),
    );
  }

  static Widget paymentHistoryCard({
    required String title,
    required String date,
    required String amount,
    String? invoice,
    String status = 'Paid',
    VoidCallback? onTap,
  }) {
    return Material(
      color: AppTheme.card,
      borderRadius: BorderRadius.circular(14),
      elevation: 1,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  CircleAvatar(
                    radius: 26,
                    backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                    child: const Icon(Icons.wifi_tethering, color: AppTheme.primary),
                  ),
                  Positioned(
                    right: -2,
                    bottom: -2,
                    child: CircleAvatar(
                      radius: 10,
                      backgroundColor: AppTheme.success,
                      child: const Icon(Icons.check, size: 12, color: Colors.white),
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    const SizedBox(height: 2),
                    Text(date, style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                    if (invoice != null && invoice.isNotEmpty)
                      Text('Invoice : $invoice', style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 10)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    amount,
                    style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  Text(status, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String formatMoney(num? v, {String symbol = '৳'}) {
    final fmt = NumberFormat('#,##0.0');
    return '$symbol ${fmt.format((v ?? 0).toDouble())}';
  }

  static Widget statusBadge(String label, Color color, {bool compact = false}) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: compact ? 8 : 10, vertical: compact ? 3 : 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: compact ? 10 : 11, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }

  static Widget sectionTitle(String text, {Widget? trailing}) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              text,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF0F172A)),
            ),
          ),
          if (trailing != null) trailing,
        ],
      ),
    );
  }

  static Widget formCard({required String title, String? subtitle, required List<Widget> children}) {
    return Container(
      decoration: cardDecoration(),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(subtitle, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          ],
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  static Widget primaryButton({
    required String label,
    required VoidCallback? onPressed,
    bool loading = false,
    Color? color,
    IconData? icon,
  }) {
    return SizedBox(
      height: 48,
      width: double.infinity,
      child: FilledButton.icon(
        onPressed: loading ? null : onPressed,
        icon: loading
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : Icon(icon ?? Icons.check),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.bold)),
        style: FilledButton.styleFrom(
          backgroundColor: color ?? AppTheme.primary,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }

  static Widget taskCard({
    required String title,
    required String status,
    required bool done,
    required VoidCallback? onComplete,
  }) {
    final color = done ? AppTheme.success : AppTheme.warning;
    return Material(
      color: AppTheme.card,
      borderRadius: BorderRadius.circular(14),
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(done ? Icons.check_circle : Icons.task_alt, color: color),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                  const SizedBox(height: 4),
                  statusBadge(status.replaceAll('_', ' '), color, compact: true),
                ],
              ),
            ),
            if (!done && onComplete != null)
              FilledButton.tonal(
                onPressed: onComplete,
                child: const Text('Done'),
              ),
          ],
        ),
      ),
    );
  }

  static Widget approvalCard({
    required String amountLine,
    required String metaLine,
    String? description,
    required VoidCallback onApprove,
    required VoidCallback onReject,
  }) {
    return Container(
      decoration: cardDecoration(),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(amountLine, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17, color: Color(0xFF0F172A))),
          const SizedBox(height: 4),
          Text(metaLine, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          if (description != null && description.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(description, style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: onReject,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppTheme.danger,
                    side: const BorderSide(color: AppTheme.danger),
                    minimumSize: const Size.fromHeight(42),
                  ),
                  child: const Text('Reject'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: onApprove,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppTheme.success,
                    minimumSize: const Size.fromHeight(42),
                  ),
                  child: const Text('Approve'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  static Widget filterChips({
    required List<(String, String)> options,
    required String selected,
    required ValueChanged<String> onSelected,
  }) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: options
            .map(
              (o) => Padding(
                padding: const EdgeInsets.only(right: 8),
                child: FilterChip(
                  label: Text(o.$2, style: const TextStyle(fontSize: 12)),
                  selected: selected == o.$1,
                  showCheckmark: false,
                  selectedColor: AppTheme.primary.withValues(alpha: 0.15),
                  onSelected: (_) => onSelected(o.$1),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  static Widget iconGridTile({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Material(
      color: AppTheme.card,
      borderRadius: BorderRadius.circular(14),
      elevation: 1,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: color, size: 26),
              ),
              const SizedBox(height: 8),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Wraps content below status bar with optional safe area for gradient headers.
class IspSafeHeader extends StatelessWidget {
  const IspSafeHeader({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: child,
    );
  }
}
