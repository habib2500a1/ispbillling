import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import 'login_screen.dart';

class StaffProfileScreen extends StatefulWidget {
  const StaffProfileScreen({super.key, required this.api, this.user});

  final ApiService api;
  final Map<String, dynamic>? user;

  @override
  State<StaffProfileScreen> createState() => _StaffProfileScreenState();
}

class _StaffProfileScreenState extends State<StaffProfileScreen> {
  final _currentCtrl = TextEditingController();
  final _newCtrl = TextEditingController();
  final _confirmCtrl = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _currentCtrl.dispose();
    _newCtrl.dispose();
    _confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> _changePassword() async {
    if (_newCtrl.text != _confirmCtrl.text) {
      showSnack(context, 'Passwords do not match', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.staffUpdatePassword(current: _currentCtrl.text, password: _newCtrl.text);
      if (mounted) showSnack(context, 'Password updated');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final u = widget.user ?? {};
    return PageScaffold(
      title: 'Profile',
      useGradientBody: true,
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: const CircleAvatar(child: Icon(Icons.person)),
              title: Text(u['name']?.toString() ?? 'Staff'),
              subtitle: Text('${u['email'] ?? ''}\n${u['user_type'] ?? ''}'),
              isThreeLine: true,
            ),
          ),
          const SizedBox(height: 20),
          const Text('Change password', style: TextStyle(fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          TextField(controller: _currentCtrl, decoration: const InputDecoration(labelText: 'Current password', border: OutlineInputBorder()), obscureText: true),
          const SizedBox(height: 8),
          TextField(controller: _newCtrl, decoration: const InputDecoration(labelText: 'New password', border: OutlineInputBorder()), obscureText: true),
          const SizedBox(height: 8),
          TextField(controller: _confirmCtrl, decoration: const InputDecoration(labelText: 'Confirm password', border: OutlineInputBorder()), obscureText: true),
          const SizedBox(height: 12),
          FilledButton(onPressed: _saving ? null : _changePassword, child: _saving ? const CircularProgressIndicator() : const Text('Update password')),
          const SizedBox(height: 24),
          OutlinedButton.icon(onPressed: _logout, icon: const Icon(Icons.logout), label: const Text('Sign out')),
          const SizedBox(height: 16),
          const Text('Database backup: use Admin panel → Settings on web.', style: TextStyle(fontSize: 11, color: Colors.grey)),
        ],
      ),
    );
  }
}
