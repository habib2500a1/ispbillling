import '../../services/api_service.dart';

/// Why a request failed — lets the UI pick the right icon/message/retry.
enum FailureType { network, timeout, unauthorized, server, notFound, unknown }

class Failure {
  const Failure(this.message, {this.type = FailureType.unknown, this.statusCode});

  final String message;
  final FailureType type;
  final int? statusCode;

  bool get isOffline => type == FailureType.network || type == FailureType.timeout;

  /// Map a thrown error (from the existing [ApiService]) into a typed Failure.
  factory Failure.from(Object error) {
    if (error is ApiException) {
      final code = error.statusCode ?? 0;
      final type = switch (code) {
        401 || 403 => FailureType.unauthorized,
        404 => FailureType.notFound,
        >= 500 => FailureType.server,
        _ => FailureType.unknown,
      };
      return Failure(error.message, type: type, statusCode: code);
    }
    final s = error.toString();
    if (s.contains('SocketException') || s.contains('Failed host lookup') || s.contains('Network')) {
      return const Failure('No internet connection. Check your network and retry.',
          type: FailureType.network);
    }
    if (s.contains('TimeoutException') || s.contains('timed out')) {
      return const Failure('The server took too long to respond. Please retry.',
          type: FailureType.timeout);
    }
    return Failure(s.length > 140 ? '${s.substring(0, 140)}…' : s);
  }
}

/// Lightweight Result so repositories never throw into the UI layer.
sealed class Result<T> {
  const Result();
  R when<R>({required R Function(T data) ok, required R Function(Failure f) err}) =>
      switch (this) {
        Ok<T>(:final data) => ok(data),
        Err<T>(:final failure) => err(failure),
      };
}

class Ok<T> extends Result<T> {
  const Ok(this.data);
  final T data;
}

class Err<T> extends Result<T> {
  const Err(this.failure);
  final Failure failure;
}

/// Run an async call and fold any thrown error into a [Failure].
Future<Result<T>> guard<T>(Future<T> Function() body) async {
  try {
    return Ok(await body());
  } catch (e) {
    return Err(Failure.from(e));
  }
}
