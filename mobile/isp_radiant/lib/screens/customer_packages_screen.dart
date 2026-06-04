import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/customer/data/customer_repository.dart';
import '../features/customer/domain/customer_models.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/isp_ui_kit.dart';

class CustomerPackagesScreen extends StatefulWidget {
  const CustomerPackagesScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerPackagesScreen> createState() => _CustomerPackagesScreenState();
}

class _CustomerPackagesScreenState extends State<CustomerPackagesScreen> {
  late final CustomerRepository _repo = CustomerRepository(widget.api);
  List<PackageOption> _packages = [];
  bool _loading = true;
  Failure? _error;
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
    final res = await _repo.packages();
    if (!mounted) return;
    res.when(
      ok: (list) => setState(() {
        _packages = list;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _request(PackageOption pkg) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Change to ${pkg.name}?'),
        content: const Text('Package change request will be sent to your ISP team.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final res = await _repo.requestPackageChange(pkg.id);
    if (!mounted) return;
    res.when(
      ok: (msg) {
        showSnack(context, msg);
        _load();
      },
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Package')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) return const SkeletonList(count: 4, rowHeight: 92);
    if (_error != null) return ErrorStateView(failure: _error!, onRetry: _load);

    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          IspUiKit.gradientHeader(
            title: 'Internet packages',
            subtitle: 'View plans · request upgrade',
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: _packages.isEmpty
                ? const EmptyStateView(icon: Icons.inventory_2_rounded, title: 'No packages available')
                : Column(
                    children: [
                      for (var i = 0; i < _packages.length; i++) ...[
                        if (i > 0) const SizedBox(height: 12),
                        _packageCard(_packages[i]),
                      ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Widget _packageCard(PackageOption p) {
    return IspUiKit.packageCard(
      title: p.name,
      speedLine: 'Download ${p.downloadMbps} Mbps · Upload ${p.uploadMbps} Mbps',
      price: '৳ ${_fmt.format(p.priceMonthly)}',
      isCurrent: p.isCurrent,
      onRequest: p.isCurrent ? null : () => _request(p),
    );
  }
}
