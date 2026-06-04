//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class ResellerInternalTicketsPostRequest {
  /// Returns a new [ResellerInternalTicketsPostRequest] instance.
  ResellerInternalTicketsPostRequest({
    required this.subject,
    required this.body,
  });

  String subject;

  String body;

  @override
  bool operator ==(Object other) => identical(this, other) || other is ResellerInternalTicketsPostRequest &&
    other.subject == subject &&
    other.body == body;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (subject.hashCode) +
    (body.hashCode);

  @override
  String toString() => 'ResellerInternalTicketsPostRequest[subject=$subject, body=$body]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'subject'] = this.subject;
      json[r'body'] = this.body;
    return json;
  }

  /// Returns a new [ResellerInternalTicketsPostRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static ResellerInternalTicketsPostRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "ResellerInternalTicketsPostRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "ResellerInternalTicketsPostRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return ResellerInternalTicketsPostRequest(
        subject: mapValueOfType<String>(json, r'subject')!,
        body: mapValueOfType<String>(json, r'body')!,
      );
    }
    return null;
  }

  static List<ResellerInternalTicketsPostRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ResellerInternalTicketsPostRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ResellerInternalTicketsPostRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, ResellerInternalTicketsPostRequest> mapFromJson(dynamic json) {
    final map = <String, ResellerInternalTicketsPostRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = ResellerInternalTicketsPostRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of ResellerInternalTicketsPostRequest-objects as value to a dart map
  static Map<String, List<ResellerInternalTicketsPostRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<ResellerInternalTicketsPostRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = ResellerInternalTicketsPostRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
    'subject',
    'body',
  };
}

