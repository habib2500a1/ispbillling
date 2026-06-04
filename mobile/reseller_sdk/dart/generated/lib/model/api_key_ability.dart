//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;


class ApiKeyAbility {
  /// Instantiate a new enum with the provided [value].
  const ApiKeyAbility._(this.value);

  /// The underlying value of this enum member.
  final String value;

  @override
  String toString() => value;

  String toJson() => value;

  static const portalPeriodCustomerPeriodView = ApiKeyAbility._(r'portal.customer.view');
  static const portalPeriodBillingPeriodView = ApiKeyAbility._(r'portal.billing.view');
  static const portalPeriodCommissionPeriodView = ApiKeyAbility._(r'portal.commission.view');
  static const portalPeriodWalletPeriodView = ApiKeyAbility._(r'portal.wallet.view');
  static const portalPeriodSettlementPeriodManage = ApiKeyAbility._(r'portal.settlement.manage');
  static const portalPeriodTicketPeriodCreate = ApiKeyAbility._(r'portal.ticket.create');
  static const portalPeriodOnuPeriodView = ApiKeyAbility._(r'portal.onu.view');
  static const portalPeriodNetworkPeriodView = ApiKeyAbility._(r'portal.network.view');
  static const portalPeriodReportsPeriodView = ApiKeyAbility._(r'portal.reports.view');
  static const portalPeriodSubResellerPeriodView = ApiKeyAbility._(r'portal.sub_reseller.view');
  static const portalPeriodCustomerPeriodTransfer = ApiKeyAbility._(r'portal.customer.transfer');
  static const portalPeriodResellerBillingPeriodView = ApiKeyAbility._(r'portal.reseller_billing.view');
  static const portalPeriodAnnouncementsPeriodView = ApiKeyAbility._(r'portal.announcements.view');

  /// List of all possible values in this [enum][ApiKeyAbility].
  static const values = <ApiKeyAbility>[
    portalPeriodCustomerPeriodView,
    portalPeriodBillingPeriodView,
    portalPeriodCommissionPeriodView,
    portalPeriodWalletPeriodView,
    portalPeriodSettlementPeriodManage,
    portalPeriodTicketPeriodCreate,
    portalPeriodOnuPeriodView,
    portalPeriodNetworkPeriodView,
    portalPeriodReportsPeriodView,
    portalPeriodSubResellerPeriodView,
    portalPeriodCustomerPeriodTransfer,
    portalPeriodResellerBillingPeriodView,
    portalPeriodAnnouncementsPeriodView,
  ];

  static ApiKeyAbility? fromJson(dynamic value) => ApiKeyAbilityTypeTransformer().decode(value);

  static List<ApiKeyAbility> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ApiKeyAbility>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ApiKeyAbility.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }
}

/// Transformation class that can [encode] an instance of [ApiKeyAbility] to String,
/// and [decode] dynamic data back to [ApiKeyAbility].
class ApiKeyAbilityTypeTransformer {
  factory ApiKeyAbilityTypeTransformer() => _instance ??= const ApiKeyAbilityTypeTransformer._();

  const ApiKeyAbilityTypeTransformer._();

  String encode(ApiKeyAbility data) => data.value;

  /// Decodes a [dynamic value][data] to a ApiKeyAbility.
  ///
  /// If [allowNull] is true and the [dynamic value][data] cannot be decoded successfully,
  /// then null is returned. However, if [allowNull] is false and the [dynamic value][data]
  /// cannot be decoded successfully, then an [UnimplementedError] is thrown.
  ///
  /// The [allowNull] is very handy when an API changes and a new enum value is added or removed,
  /// and users are still using an old app with the old code.
  ApiKeyAbility? decode(dynamic data, {bool allowNull = true}) {
    if (data != null) {
      switch (data) {
        case r'portal.customer.view': return ApiKeyAbility.portalPeriodCustomerPeriodView;
        case r'portal.billing.view': return ApiKeyAbility.portalPeriodBillingPeriodView;
        case r'portal.commission.view': return ApiKeyAbility.portalPeriodCommissionPeriodView;
        case r'portal.wallet.view': return ApiKeyAbility.portalPeriodWalletPeriodView;
        case r'portal.settlement.manage': return ApiKeyAbility.portalPeriodSettlementPeriodManage;
        case r'portal.ticket.create': return ApiKeyAbility.portalPeriodTicketPeriodCreate;
        case r'portal.onu.view': return ApiKeyAbility.portalPeriodOnuPeriodView;
        case r'portal.network.view': return ApiKeyAbility.portalPeriodNetworkPeriodView;
        case r'portal.reports.view': return ApiKeyAbility.portalPeriodReportsPeriodView;
        case r'portal.sub_reseller.view': return ApiKeyAbility.portalPeriodSubResellerPeriodView;
        case r'portal.customer.transfer': return ApiKeyAbility.portalPeriodCustomerPeriodTransfer;
        case r'portal.reseller_billing.view': return ApiKeyAbility.portalPeriodResellerBillingPeriodView;
        case r'portal.announcements.view': return ApiKeyAbility.portalPeriodAnnouncementsPeriodView;
        default:
          if (!allowNull) {
            throw ArgumentError('Unknown enum value to decode: $data');
          }
      }
    }
    return null;
  }

  /// Singleton [ApiKeyAbilityTypeTransformer] instance.
  static ApiKeyAbilityTypeTransformer? _instance;
}

