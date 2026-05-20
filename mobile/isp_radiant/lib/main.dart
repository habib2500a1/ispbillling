import 'package:flutter/material.dart';

import 'config/remote_config.dart';
import 'screens/customer_home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/staff_home_screen.dart';
import 'services/api_service.dart';
import 'theme/app_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const IspRadiantApp());
}

class IspRadiantApp extends StatelessWidget {
  const IspRadiantApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'RADIANT ISP',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      themeMode: ThemeMode.light,
      home: const SplashGate(),
    );
  }
}

class SplashGate extends StatefulWidget {
  const SplashGate({super.key});

  @override
  State<SplashGate> createState() => _SplashGateState();
}

class _SplashGateState extends State<SplashGate> {
  final _api = ApiService();
  String _status = 'Loading…';

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    await _api.loadRemoteConfig();
    final valid = await _api.validateSession();
    if (!mounted) return;

    final role = await _api.role;
    if (!mounted) return;

    if (valid && role == 'staff') {
      final mode = await _api.staffMode ?? 'admin';
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => StaffHomeScreen(api: _api, loginPayload: {}, staffMode: mode)),
      );
      return;
    }
    if (valid && role == 'customer') {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => CustomerHomeScreen(api: _api, loginPayload: {})),
      );
      return;
    }

    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => LoginScreen(api: _api)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: AppTheme.heroGradient,
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.wifi_tethering, size: 64, color: Colors.white),
              const SizedBox(height: 20),
              Text(
                RemoteConfig.appName,
                style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 24),
              const CircularProgressIndicator(color: Colors.white),
              const SizedBox(height: 12),
              Text(_status, style: const TextStyle(color: Colors.white70)),
            ],
          ),
        ),
      ),
    );
  }
}
