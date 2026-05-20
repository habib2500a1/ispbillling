import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// In-scroll profile header (avoids tall AppBar clipping on small screens).
class ProfileBanner extends StatelessWidget {
  const ProfileBanner({
    super.key,
    required this.name,
    this.subtitle,
    this.status,
    this.statusColor,
    this.leading,
  });

  final String name;
  final String? subtitle;
  final String? status;
  final Color? statusColor;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      elevation: 3,
      shadowColor: AppTheme.primary.withValues(alpha: 0.35),
      color: AppTheme.primary,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            leading ??
                CircleAvatar(
                  radius: 26,
                  backgroundColor: Colors.white24,
                  child: Text(
                    name.isNotEmpty ? name[0].toUpperCase() : '?',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 20),
                  ),
                ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 4),
                    Text(subtitle!, style: const TextStyle(color: Colors.white70, fontSize: 12)),
                  ],
                  if (status != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      status!,
                      style: TextStyle(color: statusColor ?? Colors.white70, fontSize: 12, fontWeight: FontWeight.w600),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
