import 'package:flutter/material.dart';

/// Colorful 4-column quick actions — legacy SOFTIFY dashboard grid.
class RadiantLegacyQuickActions extends StatelessWidget {
  const RadiantLegacyQuickActions({
    super.key,
    required this.actions,
    required this.onAction,
  });

  final List<Map<String, dynamic>> actions;
  final void Function(String key) onAction;

  static const _palette = [
    Color(0xFF26A69A),
    Color(0xFF42A5F5),
    Color(0xFFFF7043),
    Color(0xFF7E57C2),
    Color(0xFF8D6E63),
    Color(0xFF66BB6A),
    Color(0xFFEC407A),
    Color(0xFF29B6F6),
  ];

  IconData _icon(String? key, String? iconName) {
    switch (key) {
      case 'collect':
        return Icons.payments_outlined;
      case 'monitoring':
        return Icons.desktop_windows_outlined;
      case 'add_client':
        return Icons.person_add_alt_1_outlined;
      case 'clients':
        return Icons.fact_check_outlined;
      case 'approval':
        return Icons.approval_outlined;
      case 'billing':
        return Icons.receipt_long_outlined;
      case 'expense':
        return Icons.account_balance_wallet_outlined;
      case 'tickets':
        return Icons.confirmation_number_outlined;
      default:
        break;
    }
    switch (iconName) {
      case 'payments':
        return Icons.payments_outlined;
      case 'monitor':
        return Icons.desktop_windows_outlined;
      case 'person_add':
        return Icons.person_add_alt_1_outlined;
      case 'groups':
        return Icons.fact_check_outlined;
      case 'verified':
        return Icons.approval_outlined;
      case 'receipt':
        return Icons.receipt_long_outlined;
      case 'account_balance':
        return Icons.account_balance_wallet_outlined;
      case 'support':
        return Icons.confirmation_number_outlined;
      default:
        return Icons.apps_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (actions.isEmpty) return const SizedBox.shrink();

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: 14,
        crossAxisSpacing: 8,
        childAspectRatio: 0.82,
      ),
      itemCount: actions.length,
      itemBuilder: (context, i) {
        final action = actions[i];
        final key = action['key']?.toString() ?? '';
        final color = _palette[i % _palette.length];

        return InkWell(
          onTap: () => onAction(key),
          borderRadius: BorderRadius.circular(8),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.start,
            children: [
              Icon(_icon(key, action['icon']?.toString()), color: color, size: 34),
              const SizedBox(height: 6),
              Text(
                action['label']?.toString() ?? '',
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11, color: Color(0xFF455A64), height: 1.15),
              ),
            ],
          ),
        );
      },
    );
  }
}
