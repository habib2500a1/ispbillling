import 'package:flutter/material.dart';

import '../core/navigation/super_app_navigator.dart';
import '../core/roles/role_resolver.dart';
import '../core/roles/staff_interface.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';

/// Bottom sheet for staff users with multiple interfaces (admin / collector / NOC / technician).
Future<void> showRoleSwitcherSheet(
  BuildContext context, {
  required ApiService api,
  required RoleCapabilities capabilities,
  required String currentMode,
  Map<String, dynamic> loginPayload = const {},
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) {
      final brand = ctx.radiant;
      final text = Theme.of(ctx).textTheme;
      final bottom = MediaQuery.paddingOf(ctx).bottom;

      return Padding(
        padding: EdgeInsets.fromLTRB(12, 0, 12, bottom + 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            RadiantGlassCard(
              borderRadius: RadiantTokens.radiusXl,
              padding: EdgeInsets.zero,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ClipRRect(
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(RadiantTokens.radiusXl)),
                    child: RadiantMeshBackground(
                      bottomRadius: 0,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Switch workspace',
                              style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.4),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Your account has ${capabilities.staffInterfaces.length} roles in this app.',
                              style: text.bodySmall?.copyWith(color: brand.muted),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                    child: Column(
                      children: capabilities.staffInterfaces.map((mode) {
                        final selected = mode == currentMode;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: RadiantGlassCard(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            onTap: selected
                                ? null
                                : () async {
                                    Navigator.pop(ctx);
                                    await SuperAppNavigator.switchStaffInterface(
                                      context,
                                      api,
                                      newMode: mode,
                                      loginPayload: loginPayload,
                                    );
                                  },
                            child: Row(
                              children: [
                                Container(
                                  width: 44,
                                  height: 44,
                                  decoration: BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: selected
                                          ? [RadiantTokens.brand, RadiantTokens.accent]
                                          : [
                                              RadiantTokens.brand.withValues(alpha: 0.14),
                                              RadiantTokens.accent.withValues(alpha: 0.08),
                                            ],
                                    ),
                                    borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                                  ),
                                  child: Icon(
                                    _iconFor(mode),
                                    color: selected ? Colors.white : RadiantTokens.brand,
                                    size: 22,
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        StaffInterface.labelFor(mode),
                                        style: text.titleSmall?.copyWith(
                                          fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                                        ),
                                      ),
                                      Text(
                                        _subtitleFor(mode),
                                        style: text.bodySmall?.copyWith(color: brand.muted, fontSize: 11),
                                      ),
                                    ],
                                  ),
                                ),
                                if (selected)
                                  const RadiantStatusChip(
                                    label: 'Active',
                                    color: RadiantTokens.brand,
                                    icon: Icons.check_circle_rounded,
                                  )
                                else
                                  Icon(Icons.chevron_right_rounded, color: brand.muted, size: 22),
                              ],
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    },
  );
}

IconData _iconFor(String mode) {
  switch (mode) {
    case StaffInterface.collector:
      return Icons.account_balance_wallet_outlined;
    case StaffInterface.noc:
      return Icons.cell_tower_rounded;
    case StaffInterface.technician:
      return Icons.engineering_outlined;
    default:
      return Icons.dashboard_outlined;
  }
}

String _subtitleFor(String mode) {
  switch (mode) {
    case StaffInterface.collector:
      return 'Billing · collection · receipts';
    case StaffInterface.noc:
      return 'Network monitoring · alerts';
    case StaffInterface.technician:
      return 'Field visits · tickets';
    default:
      return 'Full staff dashboard';
  }
}
