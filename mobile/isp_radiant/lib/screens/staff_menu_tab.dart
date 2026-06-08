import 'package:flutter/material.dart';

import '../config/remote_config.dart';
import '../core/roles/role_resolver.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_module_tile.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../utils/layout.dart';
import 'staff_ai_screen.dart';
import 'staff_comms_screen.dart';
import 'staff_expense_screen.dart';
import 'staff_gis_map_screen.dart';
import 'staff_global_search_screen.dart';
import 'staff_inventory_pos_screen.dart';
import 'staff_mfs_sms_screen.dart';
import 'staff_profile_screen.dart';
import 'staff_reports_screen.dart';
import 'staff_team_discount_screen.dart';

/// Staff bottom "Menu" tab — modules, tools, profile shortcuts.
class StaffMenuTab extends StatelessWidget {
  const StaffMenuTab({
    super.key,
    required this.api,
    required this.modules,
    required this.user,
    required this.staffMode,
    required this.roleCapabilities,
    required this.loginPayload,
    required this.onModule,
    required this.onTasks,
    required this.active,
  });

  final ApiService api;
  final List<Map<String, dynamic>> modules;
  final Map<String, dynamic>? user;
  final String staffMode;
  final RoleCapabilities? roleCapabilities;
  final Map<String, dynamic> loginPayload;
  final void Function(String key) onModule;
  final VoidCallback onTasks;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final name = user?['name']?.toString() ?? 'Staff';
    final email = user?['email']?.toString() ?? '';
    final brand = context.radiant;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        RadiantScreenHeader(
          title: 'Menu',
          subtitle: '$name · ${RemoteConfig.appName}',
          compact: true,
        ),
        Padding(
          padding: pagePadding(context, top: 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              RadiantGlassCard(
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => StaffProfileScreen(
                      api: api,
                      user: user,
                      staffMode: staffMode,
                      roleCapabilities: roleCapabilities,
                      loginPayload: loginPayload,
                    ),
                  ),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: RadiantTokens.brand.withValues(alpha: 0.15),
                      child: Text(
                        name.isNotEmpty ? name[0].toUpperCase() : 'S',
                        style: context.text.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: RadiantTokens.brand,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(name, style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                          if (email.isNotEmpty)
                            Text(email, style: context.text.bodySmall?.copyWith(color: brand.muted)),
                        ],
                      ),
                    ),
                    Icon(Icons.chevron_right_rounded, color: brand.muted),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              const RadiantSectionHeader(title: 'Tools'),
              _toolRow(context, [
                _Tool(Icons.task_alt_rounded, 'Tasks', RadiantTokens.brand, onTasks),
                _Tool(Icons.search_rounded, 'Search', RadiantTokens.accent, () {
                  Navigator.push(context, MaterialPageRoute(builder: (_) => StaffGlobalSearchScreen(api: api)));
                }),
                _Tool(Icons.map_outlined, 'Network map', RadiantTokens.accentCyan, () {
                  Navigator.push(context, MaterialPageRoute(builder: (_) => StaffGisMapScreen(api: api)));
                }),
                _Tool(Icons.auto_awesome_rounded, 'AI', RadiantTokens.warning, () {
                  Navigator.push(context, MaterialPageRoute(builder: (_) => StaffAiScreen(api: api)));
                }),
              ]),
              const SizedBox(height: 16),
              if (modules.isNotEmpty) ...[
                const RadiantSectionHeader(title: 'All modules'),
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 1.05,
                  ),
                  itemCount: modules.length,
                  itemBuilder: (context, i) {
                    final m = modules[i];
                    return RadiantModuleTile(
                      title: m['title']?.toString() ?? '',
                      subtitle: m['subtitle']?.toString() ?? '',
                      icon: RadiantModuleTile.iconFromKey(m['icon']?.toString() ?? ''),
                      color: RadiantModuleTile.colorFromKey(m['color']?.toString() ?? 'blue'),
                      onTap: () => onModule(m['key']?.toString() ?? ''),
                    );
                  },
                ),
              ],
              const SizedBox(height: 16),
              const RadiantSectionHeader(title: 'More'),
              _menuLink(context, Icons.inventory_2_outlined, 'Retail POS', () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => StaffInventoryPosScreen(api: api)));
              }),
              _menuLink(context, Icons.analytics_outlined, 'Reports', () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => StaffReportsScreen(api: api)));
              }),
              _menuLink(context, Icons.sms_outlined, 'SMS & notices', () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCommsScreen(api: api)));
              }),
              _menuLink(context, Icons.account_balance_wallet_outlined, 'Expense', () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => StaffExpenseScreen(api: api)));
              }),
              if (staffMode == 'admin')
                _menuLink(context, Icons.percent_rounded, 'Collection discounts', () {
                  Navigator.push(context, MaterialPageRoute(builder: (_) => StaffTeamDiscountScreen(api: api)));
                }),
              if (RemoteConfig.mfsSmsStaff && (staffMode == 'admin' || staffMode == 'collector'))
                _menuLink(context, Icons.sms_failed_outlined, 'MFS SMS', () {
                  Navigator.push(context, MaterialPageRoute(builder: (_) => StaffMfsSmsScreen(api: api)));
                }),
              const SizedBox(height: 80),
            ],
          ),
        ),
      ],
    );
  }

  Widget _toolRow(BuildContext context, List<_Tool> tools) {
    return Row(
      children: tools
          .map(
            (t) => Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: RadiantQuickChip(icon: t.icon, label: t.label, color: t.color, onTap: t.onTap),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _menuLink(BuildContext context, IconData icon, String label, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: RadiantGlassCard(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            Icon(icon, color: RadiantTokens.brand, size: 22),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w600))),
            Icon(Icons.chevron_right_rounded, color: context.radiant.muted, size: 20),
          ],
        ),
      ),
    );
  }
}

class _Tool {
  const _Tool(this.icon, this.label, this.color, this.onTap);
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
}
