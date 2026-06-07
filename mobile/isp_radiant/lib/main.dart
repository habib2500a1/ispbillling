import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'config/server_config.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  ServerConfig.init().then((_) {
    runApp(const ProviderScope(child: IspRadiantApp()));
  });
}
