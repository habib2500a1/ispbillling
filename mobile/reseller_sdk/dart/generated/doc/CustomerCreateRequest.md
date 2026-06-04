# isp_reseller_api.model.CustomerCreateRequest

## Load the model package
```dart
import 'package:isp_reseller_api/api.dart';
```

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**name** | **String** |  | 
**phone** | **String** |  | 
**email** | **String** |  | [optional] 
**address** | **String** |  | 
**packageId** | **int** |  | 
**areaId** | **int** |  | [optional] 
**zoneId** | **int** |  | [optional] 
**customerCode** | **String** |  | [optional] 
**billingMode** | **String** |  | [optional] 
**billingDay** | **int** |  | [optional] 
**gracePeriodDays** | **int** |  | [optional] 
**joinedAt** | [**DateTime**](DateTime.md) |  | [optional] 
**notes** | **String** |  | [optional] 
**provisionMikrotik** | **bool** |  | [optional] 
**generateBill** | **bool** |  | [optional] 
**collectPayment** | **bool** |  | [optional] 
**paymentAmount** | **num** |  | [optional] 
**paymentMethod** | [**PaymentMethod**](PaymentMethod.md) |  | [optional] 

[[Back to Model list]](../README.md#documentation-for-models) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to README]](../README.md)


