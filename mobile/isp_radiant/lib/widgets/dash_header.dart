import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Demo-style blue header (admin + client).
class DashHeader extends StatelessWidget implements PreferredSizeWidget {
  const DashHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.status,
    this.statusColor,
    this.onRefresh,
    this.onLogout,
    this.actions,
  });

  final String title;
  final String? subtitle;
  final String? status;
  final Color? statusColor;
  final VoidCallback? onRefresh;
  final VoidCallback? onLogout;
  final List<Widget>? actions;

  @override
  Size get preferredSize => const Size.fromHeight(88);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      toolbarHeight: 88,
      backgroundColor: AppTheme.primary,
      foregroundColor: Colors.white,
      automaticallyImplyLeading: false,
      title: Row(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: Colors.white24,
            child: Text(
              title.isNotEmpty ? title[0].toUpperCase() : '?',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                if (subtitle != null)
                  Text(subtitle!, style: const TextStyle(fontSize: 11, color: Colors.white70)),
                if (status != null)
                  Text(
                    'Status: $status',
                    style: TextStyle(fontSize: 11, color: statusColor ?? Colors.amber),
                  ),
              ],
            ),
          ),
        ],
      ),
      actions: [
        if (onRefresh != null) IconButton(icon: const Icon(Icons.refresh), onPressed: onRefresh),
        ...?actions,
        if (onLogout != null) IconButton(icon: const Icon(Icons.logout), onPressed: onLogout),
      ],
    );
  }
}
