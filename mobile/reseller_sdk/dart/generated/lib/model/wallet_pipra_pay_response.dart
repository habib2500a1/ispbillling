//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class WalletPipraPayResponse {
  /// Returns a new [WalletPipraPayResponse] instance.
  WalletPipraPayResponse({
    this.paymentUrl,
    this.request = const {},
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? paymentUrl;

  Map<String, Object> request;

  @override
  bool operator ==(Object other) => identical(this, other) || other is WalletPipraPayResponse &&
    other.paymentUrl == paymentUrl &&
    _deepEquality.equals(other.request, request);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (paymentUrl == null ? 0 : paymentUrl!.hashCode) +
    (request.hashCode);

  @override
  String toString() => 'WalletPipraPayResponse[paymentUrl=$paymentUrl, request=$request]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.paymentUrl != null) {
      json[r'payment_url'] = this.paymentUrl;
    } else {
      json[r'payment_url'] = null;
    }
      json[r'request'] = this.request;
    return json;
  }

  /// Returns a new [WalletPipraPayResponse] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static WalletPipraPayResponse? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "WalletPipraPayResponse[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "WalletPipraPayResponse[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return WalletPipraPayResponse(
        paymentUrl: mapValueOfType<String>(json, r'payment_url'),
        request: mapCastOfType<String, Object>(json, r'request') ?? const {},
      );
    }
    return null;
  }

  static List<WalletPipraPayResponse> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <WalletPipraPayResponse>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = WalletPipraPayResponse.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, WalletPipraPayResponse> mapFromJson(dynamic json) {
    final map = <String, WalletPipraPayResponse>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = WalletPipraPayResponse.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of WalletPipraPayResponse-objects as value to a dart map
  static Map<String, List<WalletPipraPayResponse>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<WalletPipraPayResponse>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = WalletPipraPayResponse.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

