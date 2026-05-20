import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class ModuleTile extends StatelessWidget {
  const ModuleTile({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  static IconData iconFromKey(String key) {
    switch (key) {
      case 'groups':
        return Icons.groups;
      case 'receipt':
        return Icons.receipt_long;
      case 'payments':
        return Icons.payments;
      case 'inventory':
        return Icons.inventory_2;
      case 'router':
        return Icons.router;
      case 'analytics':
        return Icons.analytics;
      case 'support':
        return Icons.support_agent;
      case 'sms':
        return Icons.sms;
      case 'person':
        return Icons.person;
      default:
        return Icons.apps;
    }
  }

  static Color colorFromKey(String key) {
    switch (key) {
      case 'orange':
        return Colors.orange;
      case 'red':
        return Colors.red;
      case 'green':
        return AppTheme.success;
      case 'purple':
        return Colors.purple;
      case 'blue':
        return AppTheme.primary;
      case 'teal':
        return Colors.teal;
      case 'indigo':
        return Colors.indigo;
      case 'pink':
        return Colors.pink;
      default:
        return AppTheme.primary;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      elevation: 2,
      shadowColor: color.withValues(alpha: 0.2),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [color.withValues(alpha: 0.06), Colors.white],
            ),
          ),
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [color.withValues(alpha: 0.7), color],
                  ),
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(color: color.withValues(alpha: 0.3), blurRadius: 6, offset: const Offset(0, 2)),
                  ],
                ),
                child: Icon(icon, color: Colors.white, size: 26),
              ),
              const Spacer(),
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              const SizedBox(height: 2),
              Text(subtitle, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
            ],
          ),
        ),
      ),
    );
  }
}
