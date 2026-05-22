import 'package:flutter/material.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/isp_tab_screen.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/state_views.dart';

class StaffTasksScreen extends StatefulWidget {
  const StaffTasksScreen({super.key, required this.api, this.active = false});

  final ApiService api;
  final bool active;

  @override
  State<StaffTasksScreen> createState() => _StaffTasksScreenState();
}

class _StaffTasksScreenState extends State<StaffTasksScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  String _filter = 'all';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(StaffTasksScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final list = await widget.api.staffTasks();
      if (mounted) {
        setState(() {
          _items = list;
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load tasks');
    }
    if (mounted) setState(() => _loading = false);
  }

  List<Map<String, dynamic>> get _visible {
    if (_filter == 'pending') {
      return _items.where((t) => t['status']?.toString() != 'done').toList();
    }
    if (_filter == 'done') {
      return _items.where((t) => t['status']?.toString() == 'done').toList();
    }
    return _items;
  }

  int get _pendingCount => _items.where((t) => t['status']?.toString() != 'done').length;

  @override
  Widget build(BuildContext context) {
    final visible = _visible;

    return IspTabScreen(
      title: 'Tasks',
      subtitle: '$_pendingCount pending · ${RemoteConfig.appName}',
      loading: _loading,
      error: _error,
      onRetry: _load,
      onRefresh: _load,
      headerChild: Row(
        children: [
          _stat('Pending', '$_pendingCount', AppTheme.warning),
          _stat('Total', '${_items.length}', AppTheme.primary),
        ],
      ),
      empty: visible.isEmpty && !_loading && _error == null
          ? const EmptyState(icon: Icons.task_alt, title: 'No tasks', subtitle: 'Pull to refresh')
          : null,
      child: visible.isEmpty
          ? ListView(children: [SizedBox(height: MediaQuery.sizeOf(context).height * 0.2)])
          : ListView.separated(
              padding: pagePadding(context, top: 10),
              itemCount: visible.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, i) {
                final t = visible[i];
                final id = (t['id'] as num).toInt();
                final status = t['status']?.toString() ?? '';
                final done = status == 'done';
                return IspUiKit.taskCard(
                  title: t['title']?.toString() ?? 'Task',
                  status: status,
                  done: done,
                  onComplete: done
                      ? null
                      : () async {
                          try {
                            await widget.api.staffUpdateTask(id, 'done');
                            if (mounted) {
                              showSnack(context, 'Task completed');
                              _load();
                            }
                          } on ApiException catch (e) {
                            if (mounted) showSnack(context, e.message, isError: true);
                          }
                        },
                );
              },
            ),
    );
  }

  Widget _stat(String label, String value, Color color) {
    return Expanded(
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 4),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          children: [
            Text(value, style: TextStyle(color: color == AppTheme.warning ? Colors.amber : Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            Text(label, style: const TextStyle(color: Colors.white70, fontSize: 10)),
          ],
        ),
      ),
    );
  }
}
