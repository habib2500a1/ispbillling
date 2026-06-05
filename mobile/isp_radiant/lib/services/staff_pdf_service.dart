import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../services/api_service.dart';

/// Authenticated PDF fetch, save, and share for staff billing documents.
class StaffPdfService {
  StaffPdfService(this.api);

  final ApiService api;

  Future<File> downloadPdf(String url, {required String filename}) async {
    final token = await api.token;
    if (token == null || token.isEmpty) {
      throw ApiException('Not logged in');
    }

    final response = await http.get(
      Uri.parse(url),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/pdf',
      },
    );

    if (response.statusCode != 200) {
      throw ApiException('PDF download failed (${response.statusCode})');
    }
    if (response.bodyBytes.length < 4 ||
        String.fromCharCodes(response.bodyBytes.take(4)) != '%PDF') {
      throw ApiException('Invalid PDF response');
    }

    final dir = await getTemporaryDirectory();
    final safeName = filename.replaceAll(RegExp(r'[^\w\-.]+'), '-');
    final file = File('${dir.path}/$safeName');
    await file.writeAsBytes(response.bodyBytes, flush: true);
    return file;
  }

  Future<void> sharePdf(String url, {required String filename}) async {
    final file = await downloadPdf(url, filename: filename);
    await Share.shareXFiles([XFile(file.path)], text: 'Money receipt');
  }
}
