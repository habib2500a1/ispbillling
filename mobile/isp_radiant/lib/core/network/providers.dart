import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../services/api_service.dart';

/// The single shared [ApiService] (proven transport: 74 endpoints, token
/// refresh, timeouts). Repositories depend on this provider; it can be
/// overridden in tests with a fake client.
final apiServiceProvider = Provider<ApiService>((ref) => ApiService());
