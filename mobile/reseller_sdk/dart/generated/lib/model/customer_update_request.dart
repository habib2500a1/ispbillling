//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class CustomerUpdateRequest {
  /// Returns a new [CustomerUpdateRequest] instance.
  CustomerUpdateRequest({
    this.name,
    this.phone,
    this.email,
    this.address,
    this.packageId,
    this.areaId,
    this.zoneId,
    this.status,
    this.billingMode,
    this.gracePeriodDays,
    this.notes,
    this.provisionMikrotik,
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? name;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? phone;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? email;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? address;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? packageId;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? areaId;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? zoneId;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? status;

  CustomerUpdateRequestBillingModeEnum? billingMode;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? gracePeriodDays;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? notes;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  bool? provisionMikrotik;

  @override
  bool operator ==(Object other) => identical(this, other) || other is CustomerUpdateRequest &&
    other.name == name &&
    other.phone == phone &&
    other.email == email &&
    other.address == address &&
    other.packageId == packageId &&
    other.areaId == areaId &&
    other.zoneId == zoneId &&
    other.status == status &&
    other.billingMode == billingMode &&
    other.gracePeriodDays == gracePeriodDays &&
    other.notes == notes &&
    other.provisionMikrotik == provisionMikrotik;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (name == null ? 0 : name!.hashCode) +
    (phone == null ? 0 : phone!.hashCode) +
    (email == null ? 0 : email!.hashCode) +
    (address == null ? 0 : address!.hashCode) +
    (packageId == null ? 0 : packageId!.hashCode) +
    (areaId == null ? 0 : areaId!.hashCode) +
    (zoneId == null ? 0 : zoneId!.hashCode) +
    (status == null ? 0 : status!.hashCode) +
    (billingMode == null ? 0 : billingMode!.hashCode) +
    (gracePeriodDays == null ? 0 : gracePeriodDays!.hashCode) +
    (notes == null ? 0 : notes!.hashCode) +
    (provisionMikrotik == null ? 0 : provisionMikrotik!.hashCode);

  @override
  String toString() => 'CustomerUpdateRequest[name=$name, phone=$phone, email=$email, address=$address, packageId=$packageId, areaId=$areaId, zoneId=$zoneId, status=$status, billingMode=$billingMode, gracePeriodDays=$gracePeriodDays, notes=$notes, provisionMikrotik=$provisionMikrotik]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.name != null) {
      json[r'name'] = this.name;
    } else {
      json[r'name'] = null;
    }
    if (this.phone != null) {
      json[r'phone'] = this.phone;
    } else {
      json[r'phone'] = null;
    }
    if (this.email != null) {
      json[r'email'] = this.email;
    } else {
      json[r'email'] = null;
    }
    if (this.address != null) {
      json[r'address'] = this.address;
    } else {
      json[r'address'] = null;
    }
    if (this.packageId != null) {
      json[r'package_id'] = this.packageId;
    } else {
      json[r'package_id'] = null;
    }
    if (this.areaId != null) {
      json[r'area_id'] = this.areaId;
    } else {
      json[r'area_id'] = null;
    }
    if (this.zoneId != null) {
      json[r'zone_id'] = this.zoneId;
    } else {
      json[r'zone_id'] = null;
    }
    if (this.status != null) {
      json[r'status'] = this.status;
    } else {
      json[r'status'] = null;
    }
    if (this.billingMode != null) {
      json[r'billing_mode'] = this.billingMode;
    } else {
      json[r'billing_mode'] = null;
    }
    if (this.gracePeriodDays != null) {
      json[r'grace_period_days'] = this.gracePeriodDays;
    } else {
      json[r'grace_period_days'] = null;
    }
    if (this.notes != null) {
      json[r'notes'] = this.notes;
    } else {
      json[r'notes'] = null;
    }
    if (this.provisionMikrotik != null) {
      json[r'provision_mikrotik'] = this.provisionMikrotik;
    } else {
      json[r'provision_mikrotik'] = null;
    }
    return json;
  }

  /// Returns a new [CustomerUpdateRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static CustomerUpdateRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "CustomerUpdateRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "CustomerUpdateRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return CustomerUpdateRequest(
        name: mapValueOfType<String>(json, r'name'),
        phone: mapValueOfType<String>(json, r'phone'),
        email: mapValueOfType<String>(json, r'email'),
        address: mapValueOfType<String>(json, r'address'),
        packageId: mapValueOfType<int>(json, r'package_id'),
        areaId: mapValueOfType<int>(json, r'area_id'),
        zoneId: mapValueOfType<int>(json, r'zone_id'),
        status: mapValueOfType<String>(json, r'status'),
        billingMode: CustomerUpdateRequestBillingModeEnum.fromJson(json[r'billing_mode']),
        gracePeriodDays: mapValueOfType<int>(json, r'grace_period_days'),
        notes: mapValueOfType<String>(json, r'notes'),
        provisionMikrotik: mapValueOfType<bool>(json, r'provision_mikrotik'),
      );
    }
    return null;
  }

  static List<CustomerUpdateRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <CustomerUpdateRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = CustomerUpdateRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, CustomerUpdateRequest> mapFromJson(dynamic json) {
    final map = <String, CustomerUpdateRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = CustomerUpdateRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of CustomerUpdateRequest-objects as value to a dart map
  static Map<String, List<CustomerUpdateRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<CustomerUpdateRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = CustomerUpdateRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}


class CustomerUpdateRequestBillingModeEnum {
  /// Instantiate a new enum with the provided [value].
  const CustomerUpdateRequestBillingModeEnum._(this.value);

  /// The underlying value of this enum member.
  final String value;

  @override
  String toString() => value;

  String toJson() => value;

  static const prepaid = CustomerUpdateRequestBillingModeEnum._(r'prepaid');
  static const postpaid = CustomerUpdateRequestBillingModeEnum._(r'postpaid');

  /// List of all possible values in this [enum][CustomerUpdateRequestBillingModeEnum].
  static const values = <CustomerUpdateRequestBillingModeEnum>[
    prepaid,
    postpaid,
  ];

  static CustomerUpdateRequestBillingModeEnum? fromJson(dynamic value) => CustomerUpdateRequestBillingModeEnumTypeTransformer().decode(value);

  static List<CustomerUpdateRequestBillingModeEnum> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <CustomerUpdateRequestBillingModeEnum>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = CustomerUpdateRequestBillingModeEnum.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }
}

/// Transformation class that can [encode] an instance of [CustomerUpdateRequestBillingModeEnum] to String,
/// and [decode] dynamic data back to [CustomerUpdateRequestBillingModeEnum].
class CustomerUpdateRequestBillingModeEnumTypeTransformer {
  factory CustomerUpdateRequestBillingModeEnumTypeTransformer() => _instance ??= const CustomerUpdateRequestBillingModeEnumTypeTransformer._();

  const CustomerUpdateRequestBillingModeEnumTypeTransformer._();

  String encode(CustomerUpdateRequestBillingModeEnum data) => data.value;

  /// Decodes a [dynamic value][data] to a CustomerUpdateRequestBillingModeEnum.
  ///
  /// If [allowNull] is true and the [dynamic value][data] cannot be decoded successfully,
  /// then null is returned. However, if [allowNull] is false and the [dynamic value][data]
  /// cannot be decoded successfully, then an [UnimplementedError] is thrown.
  ///
  /// The [allowNull] is very handy when an API changes and a new enum value is added or removed,
  /// and users are still using an old app with the old code.
  CustomerUpdateRequestBillingModeEnum? decode(dynamic data, {bool allowNull = true}) {
    if (data != null) {
      switch (data) {
        case r'prepaid': return CustomerUpdateRequestBillingModeEnum.prepaid;
        case r'postpaid': return CustomerUpdateRequestBillingModeEnum.postpaid;
        default:
          if (!allowNull) {
            throw ArgumentError('Unknown enum value to decode: $data');
          }
      }
    }
    return null;
  }

  /// Singleton [CustomerUpdateRequestBillingModeEnumTypeTransformer] instance.
  static CustomerUpdateRequestBillingModeEnumTypeTransformer? _instance;
}


