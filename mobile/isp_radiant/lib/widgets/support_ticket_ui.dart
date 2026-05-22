import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/app_theme.dart';
import 'isp_ui_kit.dart';

/// Professional support / ticket UI (list, detail header, chat, actions).
class SupportTicketUi {
  static Color statusColor(String? status) {
    switch (status) {
      case 'open':
        return AppTheme.warning;
      case 'in_progress':
        return AppTheme.info;
      case 'pending':
        return AppTheme.accent;
      case 'resolved':
        return AppTheme.success;
      case 'closed':
        return const Color(0xFF64748B);
      default:
        return const Color(0xFF94A3B8);
    }
  }

  static String statusLabel(String? status) {
    if (status == null || status.isEmpty) return 'Unknown';
    return status.split('_').map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}').join(' ');
  }

  static Color priorityColor(String? p) {
    switch (p) {
      case 'critical':
        return AppTheme.danger;
      case 'high':
        return Colors.deepOrange;
      case 'medium':
        return AppTheme.warning;
      default:
        return const Color(0xFF94A3B8);
    }
  }

  static Widget statusChip(String? status, {bool compact = false}) {
    final c = statusColor(status);
    return Container(
      padding: EdgeInsets.symmetric(horizontal: compact ? 8 : 10, vertical: compact ? 3 : 4),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: c.withValues(alpha: 0.35)),
      ),
      child: Text(
        statusLabel(status),
        style: TextStyle(fontSize: compact ? 10 : 11, fontWeight: FontWeight.w700, color: c),
      ),
    );
  }

  static Widget priorityChip(String? priority) {
    final c = priorityColor(priority);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        (priority ?? 'medium').toUpperCase(),
        style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: c, letterSpacing: 0.5),
      ),
    );
  }

  static Widget ticketListCard({
    required Map<String, dynamic> ticket,
    required VoidCallback onTap,
  }) {
    final status = ticket['status']?.toString();
    final subject = ticket['subject']?.toString() ?? 'Ticket';
    final number = ticket['ticket_number']?.toString() ?? '#${ticket['id']}';
    final customer = ticket['customer_name']?.toString() ?? '';
    final assignee = ticket['assignee_name']?.toString();
    final priority = ticket['priority']?.toString();
    final created = _formatWhen(ticket['created_at']);

    return Material(
      color: AppTheme.card,
      borderRadius: BorderRadius.circular(16),
      elevation: 1,
      shadowColor: Colors.black.withValues(alpha: 0.06),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          statusColor(status).withValues(alpha: 0.2),
                          AppTheme.primary.withValues(alpha: 0.15),
                        ],
                      ),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(Icons.support_agent, color: statusColor(status), size: 24),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          subject,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '$number · $customer',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: Color(0xFFCBD5E1)),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  statusChip(status, compact: true),
                  const SizedBox(width: 6),
                  priorityChip(priority),
                  const Spacer(),
                  if (created != null && created.isNotEmpty)
                    Text(created, style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(
                    assignee != null && assignee.isNotEmpty ? Icons.person : Icons.person_off_outlined,
                    size: 14,
                    color: assignee != null ? AppTheme.primary : const Color(0xFF94A3B8),
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      assignee != null && assignee.isNotEmpty ? 'Assigned: $assignee' : 'Unassigned',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: assignee != null ? AppTheme.primary : const Color(0xFF94A3B8),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static Widget detailHeader({
    required Map<String, dynamic> ticket,
    VoidCallback? onCall,
  }) {
    final status = ticket['status']?.toString();
    final number = ticket['ticket_number']?.toString() ?? '';
    final customer = ticket['customer_name']?.toString() ?? '';
    final code = ticket['customer_code']?.toString();
    final phone = ticket['customer_phone']?.toString();
    final assignee = ticket['assignee_name']?.toString();
    final dept = ticket['department']?.toString().replaceAll('_', ' ');

    return Container(
      decoration: IspUiKit.cardDecoration(tint: const Color(0xFFF8FAFC)),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              statusChip(status),
              const SizedBox(width: 8),
              priorityChip(ticket['priority']?.toString()),
              if (number.isNotEmpty) ...[
                const Spacer(),
                Text(number, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF64748B))),
              ],
            ],
          ),
          const SizedBox(height: 12),
          Text(customer, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
          if (code != null && code.isNotEmpty)
            Text('ID: $code', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          if (dept != null && dept.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(dept, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(Icons.engineering, size: 16, color: AppTheme.primary),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  assignee != null && assignee.isNotEmpty ? assignee : 'Not assigned',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: assignee != null ? const Color(0xFF0F172A) : const Color(0xFF94A3B8),
                  ),
                ),
              ),
              if (phone != null && phone.isNotEmpty && onCall != null)
                TextButton.icon(
                  onPressed: onCall,
                  icon: const Icon(Icons.phone, size: 18),
                  label: const Text('Call'),
                  style: TextButton.styleFrom(foregroundColor: AppTheme.success),
                ),
            ],
          ),
        ],
      ),
    );
  }

  static Widget actionBar({
    required bool canClose,
    required bool isClosed,
    required VoidCallback onAssign,
    required VoidCallback onInProgress,
    required VoidCallback onResolve,
    required VoidCallback onClose,
    VoidCallback? onAssignMe,
  }) {
    if (isClosed) {
      return const SizedBox.shrink();
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            _actionBtn(Icons.person_add_alt_1, 'Assign', AppTheme.primary, onAssign),
            if (onAssignMe != null) ...[
              const SizedBox(width: 8),
              _actionBtn(Icons.person_pin, 'Assign me', AppTheme.teal, onAssignMe),
            ],
            const SizedBox(width: 8),
            _actionBtn(Icons.play_arrow_rounded, 'In progress', AppTheme.info, onInProgress),
            const SizedBox(width: 8),
            _actionBtn(Icons.check_circle_outline, 'Resolve', AppTheme.success, onResolve),
            if (canClose) ...[
              const SizedBox(width: 8),
              _actionBtn(Icons.lock_outline, 'Close', const Color(0xFF64748B), onClose),
            ],
          ],
        ),
      ),
    );
  }

  static Widget _actionBtn(IconData icon, String label, Color color, VoidCallback onTap) {
    return Material(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(width: 6),
              Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: color)),
            ],
          ),
        ),
      ),
    );
  }

  static Widget chatBubble({
    required String author,
    required String body,
    required bool isStaffSide,
    required bool isInternal,
    String? time,
  }) {
    final align = isStaffSide ? Alignment.centerRight : Alignment.centerLeft;
    final bg = isStaffSide
        ? AppTheme.primary.withValues(alpha: 0.12)
        : const Color(0xFFE2E8F0);
    final border = isStaffSide
        ? Border.all(color: AppTheme.primary.withValues(alpha: 0.2))
        : null;

    return Align(
      alignment: align,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        constraints: const BoxConstraints(maxWidth: 320),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(14),
            topRight: const Radius.circular(14),
            bottomLeft: Radius.circular(isStaffSide ? 14 : 4),
            bottomRight: Radius.circular(isStaffSide ? 4 : 14),
          ),
          border: border,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(author, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700)),
                if (isInternal) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.warning.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: const Text('Internal', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: AppTheme.warning)),
                  ),
                ],
                if (time != null) ...[
                  const SizedBox(width: 8),
                  Text(time, style: const TextStyle(fontSize: 9, color: Color(0xFF94A3B8))),
                ],
              ],
            ),
            const SizedBox(height: 6),
            Text(body, style: const TextStyle(fontSize: 14, height: 1.35)),
          ],
        ),
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

  static String? _formatWhen(dynamic iso) {
    if (iso == null) return null;
    try {
      final dt = DateTime.parse(iso.toString()).toLocal();
      final now = DateTime.now();
      if (now.difference(dt).inDays < 1) {
        return DateFormat.jm().format(dt);
      }
      return DateFormat('d MMM').format(dt);
    } catch (_) {
      return null;
    }
  }
}
