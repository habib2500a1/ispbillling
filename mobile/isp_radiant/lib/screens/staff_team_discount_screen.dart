import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

/// Admin: set collection discount limits per staff member.
class StaffTeamDiscountScreen extends StatefulWidget {
  const StaffTeamDiscountScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffTeamDiscountScreen> createState() => _StaffTeamDiscountScreenState();
}

class _StaffTeamDiscountScreenState extends State<StaffTeamDiscountScreen> {
  List<Map<String, dynamic>> _staff = [];
  Map<String, dynamic>? _global;
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final body = await widget.api.staffTeamDiscounts();
      if (!mounted) return;
      setState(() {
        _global = body['global'] as Map<String, dynamic>?;
        _staff = (body['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load team');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _editStaff(Map<String, dynamic> s) async {
    final cd = Map<String, dynamic>.from(s['collection_discount'] as Map? ?? {});
    var enabled = cd['enabled'] == true;
    final maxBdtCtrl = TextEditingController(text: cd['max_discount_bdt']?.toString() ?? '');
    final maxPctCtrl = TextEditingController(text: cd['max_discount_percent_of_due']?.toString() ?? '');

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 16, right: 16, top: 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s['name']?.toString() ?? 'Staff', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            Text(s['email']?.toString() ?? '', style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
            const SizedBox(height: 16),
            SwitchListTile(
              value: enabled,
              onChanged: (v) => enabled = v,
              title: const Text('Allow collection discount'),
              contentPadding: EdgeInsets.zero,
            ),
            TextField(
              controller: maxBdtCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Max discount BDT (empty = global)', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: maxPctCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Max % of due (empty = global)', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Save'),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );

    if (saved != true || !mounted) return;

    try {
      await widget.api.updateStaffTeamDiscount(
        (s['id'] as num).toInt(),
        enabled: enabled,
        maxBdt: double.tryParse(maxBdtCtrl.text.trim()),
        maxPercent: double.tryParse(maxPctCtrl.text.trim()),
      );
      if (mounted) {
        showSnack(context, 'Saved');
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Staff discounts',
      useGradientBody: true,
      body: _loading
          ? const ListLoading()
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFF7C3AED), Color(0xFFEC4899)],
                        ),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Global limits', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 6),
                          Text(
                            'Max ${_fmt.format(_global?['max_discount_bdt'] ?? 0)} BDT · ${_global?['max_discount_percent_of_due'] ?? 0}% of due',
                            style: const TextStyle(color: Colors.white70, fontSize: 13),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Web: Collection discount settings for presets',
                            style: TextStyle(color: Colors.white60, fontSize: 11),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text('Per staff', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    ..._staff.map((s) {
                      final cd = s['collection_discount'] as Map<String, dynamic>? ?? {};
                      final on = cd['enabled'] == true;
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: on ? Colors.deepOrange.withValues(alpha: 0.2) : Colors.grey.shade200,
                            child: Icon(Icons.percent, color: on ? Colors.deepOrange : Colors.grey),
                          ),
                          title: Text(s['name']?.toString() ?? ''),
                          subtitle: Text(
                            on
                                ? 'Max ${cd['max_discount_bdt'] ?? 'global'} BDT · ${cd['max_discount_percent_of_due'] ?? 'global'}%'
                                : 'Discount off',
                            style: const TextStyle(fontSize: 12),
                          ),
                          trailing: const Icon(Icons.edit),
                          onTap: () => _editStaff(s),
                        ),
                      );
                    }),
                  ],
                ),
    );
  }
}
