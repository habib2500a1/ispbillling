import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/isp_ui_kit.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_gis_map_screen.dart';

/// Unified search: customers (staff API) + GIS index (ONU/OLT/customer pins).
class StaffGlobalSearchScreen extends StatefulWidget {
  const StaffGlobalSearchScreen({
    super.key,
    required this.api,
    this.embedded = false,
    this.technicianMode = false,
  });

  final ApiService api;
  final bool embedded;
  final bool technicianMode;

  @override
  State<StaffGlobalSearchScreen> createState() => _StaffGlobalSearchScreenState();
}

class _StaffGlobalSearchScreenState extends State<StaffGlobalSearchScreen> {
  final _ctrl = TextEditingController();
  Timer? _debounce;
  bool _loading = false;
  List<Map<String, dynamic>> _customers = [];
  List<Map<String, dynamic>> _gis = [];

  @override
  void initState() {
    super.initState();
    _ctrl.addListener(_onChanged);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _ctrl.removeListener(_onChanged);
    _ctrl.dispose();
    super.dispose();
  }

  void _onChanged() {
    _debounce?.cancel();
    final q = _ctrl.text.trim();
    if (q.length < 2) {
      setState(() {
        _customers = [];
        _gis = [];
      });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 280), () => _search(q));
  }

  Future<void> _search(String q) async {
    setState(() => _loading = true);
    try {
      final results = await Future.wait([
        widget.api.searchCustomers(q),
        widget.api.staffGisSearch(q),
      ]);
      if (mounted) {
        setState(() {
          _customers = results[0];
          _gis = results[1];
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        showSnack(context, e.message, isError: true);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openMap(Map<String, dynamic> row) async {
    final lat = row['lat'] as num?;
    final lng = row['lng'] as num?;
    if (lat == null || lng == null) {
      showSnack(context, 'No coordinates on map', isError: true);
      return;
    }
    final uri = Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Future<void> _openCustomer(int id) async {
    if (!mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final body = ListView(
      padding: widget.embedded ? pagePadding(context) : EdgeInsets.zero,
      children: [
        if (!widget.embedded)
          IspUiKit.gradientHeader(
            title: 'Global search',
            subtitle: 'Customer · invoice · ticket · ONU · OLT',
            trailing: [
              IconButton(
                icon: const Icon(Icons.map_outlined, color: Colors.white),
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffGisMapScreen(api: widget.api)),
                ),
              ),
            ],
          ),
        Padding(
          padding: widget.embedded ? EdgeInsets.zero : pagePadding(context),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 8),
              IspUiKit.searchBar(
                controller: _ctrl,
                hint: 'Name, phone, code, ONU, OLT…',
                loading: _loading,
                onSearch: () => _search(_ctrl.text.trim()),
                onClear: () => _ctrl.clear(),
              ),
              if (_customers.isNotEmpty) ...[
                const SizedBox(height: 16),
                const Text('Customers', style: TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                ..._customers.map((c) {
                  final id = (c['id'] as num).toInt();
                  return ListTile(
                    leading: const CircleAvatar(child: Icon(Icons.person)),
                    title: Text(c['name']?.toString() ?? c['username']?.toString() ?? 'Customer'),
                    subtitle: Text('${c['code'] ?? ''} · ${c['phone'] ?? ''}'.trim()),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => _openCustomer(id),
                  );
                }),
              ],
              if (_gis.isNotEmpty) ...[
                const SizedBox(height: 16),
                const Text('Network / GIS', style: TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                ..._gis.map((g) {
                  final type = g['type']?.toString() ?? 'node';
                  return ListTile(
                    leading: Icon(_iconForType(type), color: AppTheme.primary),
                    title: Text(g['label']?.toString() ?? 'Result'),
                    subtitle: Text('${type.toUpperCase()}${g['login'] != null ? ' · ${g['login']}' : ''}'),
                    trailing: (g['lat'] != null && g['lng'] != null)
                        ? IconButton(icon: const Icon(Icons.map_outlined), onPressed: () => _openMap(g))
                        : null,
                    onTap: () {
                      if (g['type'] == 'customer' && g['id'] != null) {
                        _openCustomer((g['id'] as num).toInt());
                      } else if (g['lat'] != null) {
                        _openMap(g);
                      }
                    },
                  );
                }),
              ],
              if (!_loading && _ctrl.text.trim().length >= 2 && _customers.isEmpty && _gis.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(32),
                  child: Text('No results', textAlign: TextAlign.center),
                ),
            ],
          ),
        ),
      ],
    );

    if (widget.embedded) return body;
    return Scaffold(body: body);
  }

  IconData _iconForType(String type) {
    switch (type) {
      case 'onu':
        return Icons.router_outlined;
      case 'olt':
        return Icons.dns_outlined;
      case 'splitter':
        return Icons.call_split;
      default:
        return Icons.place_outlined;
    }
  }
}
