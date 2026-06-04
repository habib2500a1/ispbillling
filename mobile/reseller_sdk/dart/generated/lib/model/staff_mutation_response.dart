//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class StaffMutationResponse {
  /// Returns a new [StaffMutationResponse] instance.
  StaffMutationResponse({
    this.message,
    this.staff,
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? message;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  StaffMember? staff;

  @override
  bool operator ==(Object other) => identical(this, other) || other is StaffMutationResponse &&
    other.message == message &&
    other.staff == staff;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (message == null ? 0 : message!.hashCode) +
    (staff == null ? 0 : staff!.hashCode);

  @override
  String toString() => 'StaffMutationResponse[message=$message, staff=$staff]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.message != null) {
      json[r'message'] = this.message;
    } else {
      json[r'message'] = null;
    }
    if (this.staff != null) {
      json[r'staff'] = this.staff;
    } else {
      json[r'staff'] = null;
    }
    return json;
  }

  /// Returns a new [StaffMutationResponse] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static StaffMutationResponse? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "StaffMutationResponse[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "StaffMutationResponse[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return StaffMutationResponse(
        message: mapValueOfType<String>(json, r'message'),
        staff: StaffMember.fromJson(json[r'staff']),
      );
    }
    return null;
  }

  static List<StaffMutationResponse> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <StaffMutationResponse>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = StaffMutationResponse.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, StaffMutationResponse> mapFromJson(dynamic json) {
    final map = <String, StaffMutationResponse>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = StaffMutationResponse.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of StaffMutationResponse-objects as value to a dart map
  static Map<String, List<StaffMutationResponse>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<StaffMutationResponse>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = StaffMutationResponse.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

