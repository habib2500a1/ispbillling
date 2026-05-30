import 'package:flutter/material.dart';

import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/customer/data/customer_repository.dart';
import '../features/customer/domain/customer_models.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

class CustomerOnuScreen extends StatefulWidget {
  const CustomerOnuScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerOnuScreen> createState() => _CustomerOnuScreenState();
}

class _CustomerOnuScreenState extends State<CustomerOnuScreen> {
  late final CustomerRepository _repo = CustomerRepository(widget.api);
  OnuStatus? _onu;
  bool _loading = true;
  Failure? _error;
  bool _rebooting = false;

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
    final res = await _repo.onuStatus();
    if (!mounted) return;
    res.when(
      ok: (o) => setState(() {
        _onu = o;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _reboot() async {
    setState(() => _rebooting = true);
    final res = await _repo.rebootOnu();
    if (!mounted) return;
    setState(() => _rebooting = false);
    res.when(
      ok: (msg) => showSnack(context, msg),
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'ONU Status',
      useGradientBody: true,
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return ListView(padding: const EdgeInsets.all(16), children: const [
        SkeletonCard(height: 90),
        SizedBox(height: 12),
        SkeletonCard(height: 56),
        SizedBox(height: 8),
        SkeletonCard(height: 56),
      ]);
    }
    if (_error != null && _onu == null) return ErrorStateView(failure: _error!, onRetry: _load);

    final o = _onu ?? OnuStatus.empty;
    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          AppCard(
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: (o.linked ? DesignTokens.success : context.brand.textMuted)
                        .withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(DesignTokens.radiusSm),
                  ),
                  child: Icon(o.linked ? Icons.router_rounded : Icons.router_outlined,
                      color: o.linked ? DesignTokens.success : context.brand.textMuted),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(o.linked ? o.label : 'No ONU linked',
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                      if ((o.serial.isNotEmpty) || (o.message.isNotEmpty))
                        Text(o.serial.isNotEmpty ? o.serial : o.message,
                            style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
                    ],
                  ),
                ),
                if (o.linked)
                  StatusPill(label: o.status, color: DesignTokens.success),
              ],
            ),
          ),
          if (o.linked) ...[
            const SizedBox(height: 12),
            AppCard(
              child: Column(
                children: [
                  _row(context, 'RX power', o.rxDbm),
                  Divider(height: 18, color: context.brand.border),
                  _row(context, 'TX power', o.txDbm),
                  Divider(height: 18, color: context.brand.border),
                  _row(context, 'Status', o.status),
                ],
              ),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _rebooting ? null : _reboot,
              icon: _rebooting
                  ? const SizedBox(
                      width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.restart_alt_rounded),
              label: const Text('Request ONU reboot'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _row(BuildContext context, String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(color: context.brand.textMuted)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    );
  }
}
