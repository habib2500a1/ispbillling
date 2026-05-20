import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/layout.dart';
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
      if (mounted) setState(() {
        _items = list;
        _error = null;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load tasks');
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(child: Padding(padding: const EdgeInsets.all(24), child: ErrorBanner(message: _error!, onRetry: _load)));
    }
    if (_items.isEmpty) {
      return const EmptyState(icon: Icons.task_alt, title: 'No tasks', subtitle: 'Pull to refresh');
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: pagePadding(context, top: 8),
        itemCount: _items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 6),
        itemBuilder: (context, i) {
          final t = _items[i];
          final id = (t['id'] as num).toInt();
          final status = t['status']?.toString() ?? '';
          final done = status == 'done';
          return Card(
            child: ListTile(
              title: Text(t['title']?.toString() ?? 'Task'),
              subtitle: Text(status),
              trailing: done
                  ? const Icon(Icons.check_circle, color: Colors.green)
                  : IconButton(
                      icon: const Icon(Icons.task_alt),
                      tooltip: 'Mark complete',
                      onPressed: () async {
                        try {
                          await widget.api.staffUpdateTask(id, 'done');
                          if (context.mounted) _load();
                        } on ApiException catch (e) {
                          if (context.mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
                          }
                        }
                      },
                    ),
            ),
          );
        },
      ),
    );
  }
}
