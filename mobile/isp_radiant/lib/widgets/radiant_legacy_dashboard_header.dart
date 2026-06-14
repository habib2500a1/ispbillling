import 'package:flutter/material.dart';

/// Blue profile header — legacy Radiant / SOFTIFY staff dashboard.
class RadiantLegacyDashboardHeader extends StatelessWidget {
  const RadiantLegacyDashboardHeader({
    super.key,
    required this.name,
    required this.userType,
    required this.status,
    this.onSearch,
    this.onNotifications,
    this.onMenu,
    this.avatarUrl,
  });

  static const Color primaryBlue = Color(0xFF4267B2);

  final String name;
  final String userType;
  final String status;
  final VoidCallback? onSearch;
  final VoidCallback? onNotifications;
  final VoidCallback? onMenu;
  final String? avatarUrl;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;

    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(16, top + 10, 12, 18),
      decoration: const BoxDecoration(
        color: primaryBlue,
        boxShadow: [
          BoxShadow(color: Color(0x22000000), blurRadius: 8, offset: Offset(0, 3)),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          _Avatar(name: name, avatarUrl: avatarUrl),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'User Type: $userType',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
                RichText(
                  text: TextSpan(
                    style: const TextStyle(fontSize: 12, color: Colors.white70),
                    children: [
                      const TextSpan(text: 'Status: '),
                      TextSpan(
                        text: status,
                        style: TextStyle(
                          color: status.toLowerCase() == 'active' ? const Color(0xFFFFD54F) : Colors.white,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          _HeaderIcon(icon: Icons.search, onTap: onSearch),
          _HeaderIcon(icon: Icons.notifications_none, onTap: onNotifications),
          _HeaderIcon(icon: Icons.menu, onTap: onMenu),
        ],
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name, this.avatarUrl});

  final String name;
  final String? avatarUrl;

  @override
  Widget build(BuildContext context) {
    if (avatarUrl != null && avatarUrl!.isNotEmpty) {
      return CircleAvatar(
        radius: 28,
        backgroundColor: Colors.white24,
        backgroundImage: NetworkImage(avatarUrl!),
      );
    }

    return CircleAvatar(
      radius: 28,
      backgroundColor: Colors.white24,
      child: Text(
        name.isNotEmpty ? name[0].toUpperCase() : '?',
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 22),
      ),
    );
  }
}

class _HeaderIcon extends StatelessWidget {
  const _HeaderIcon({required this.icon, this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onTap,
      icon: Icon(icon, color: Colors.white, size: 24),
      padding: const EdgeInsets.all(6),
      constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
    );
  }
}
