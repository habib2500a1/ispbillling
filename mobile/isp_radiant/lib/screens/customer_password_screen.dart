import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

class CustomerPasswordScreen extends StatefulWidget {
  const CustomerPasswordScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerPasswordScreen> createState() => _CustomerPasswordScreenState();
}

class _CustomerPasswordScreenState extends State<CustomerPasswordScreen> {
  final _current = TextEditingController();
  final _pass = TextEditingController();
  final _confirm = TextEditingController();
  bool _loading = false;

  Future<void> _submit() async {
    if (_pass.text.length < 6) {
      showSnack(context, 'Password min 6 characters', isError: true);
      return;
    }
    if (_pass.text != _confirm.text) {
      showSnack(context, 'Passwords do not match', isError: true);
      return;
    }
    setState(() => _loading = true);
    try {
      await widget.api.updatePassword(current: _current.text, password: _pass.text);
      if (mounted) {
        showSnack(context, 'Password updated');
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Change password',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  const Icon(Icons.lock_reset, size: 48, color: AppTheme.primary),
                  const SizedBox(height: 16),
                  TextField(controller: _current, obscureText: true, decoration: const InputDecoration(labelText: 'Current password')),
                  const SizedBox(height: 12),
                  TextField(controller: _pass, obscureText: true, decoration: const InputDecoration(labelText: 'New password')),
                  const SizedBox(height: 12),
                  TextField(controller: _confirm, obscureText: true, decoration: const InputDecoration(labelText: 'Confirm password')),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: _loading ? null : _submit,
                    style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                    child: _loading
                        ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Update password'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
