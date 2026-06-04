//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class PaginatedCustomers {
  /// Returns a new [PaginatedCustomers] instance.
  PaginatedCustomers({
    this.data = const [],
    this.links = const {},
    this.meta = const {},
  });

  List<Customer> data;

  Map<String, Object> links;

  Map<String, Object> meta;

  @override
  bool operator ==(Object other) => identical(this, other) || other is PaginatedCustomers &&
    _deepEquality.equals(other.data, data) &&
    _deepEquality.equals(other.links, links) &&
    _deepEquality.equals(other.meta, meta);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (data.hashCode) +
    (links.hashCode) +
    (meta.hashCode);

  @override
  String toString() => 'PaginatedCustomers[data=$data, links=$links, meta=$meta]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'data'] = this.data;
      json[r'links'] = this.links;
      json[r'meta'] = this.meta;
    return json;
  }

  /// Returns a new [PaginatedCustomers] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static PaginatedCustomers? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "PaginatedCustomers[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "PaginatedCustomers[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return PaginatedCustomers(
        data: Customer.listFromJson(json[r'data']),
        links: mapCastOfType<String, Object>(json, r'links') ?? const {},
        meta: mapCastOfType<String, Object>(json, r'meta') ?? const {},
      );
    }
    return null;
  }

  static List<PaginatedCustomers> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <PaginatedCustomers>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = PaginatedCustomers.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, PaginatedCustomers> mapFromJson(dynamic json) {
    final map = <String, PaginatedCustomers>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = PaginatedCustomers.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of PaginatedCustomers-objects as value to a dart map
  static Map<String, List<PaginatedCustomers>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<PaginatedCustomers>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = PaginatedCustomers.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

