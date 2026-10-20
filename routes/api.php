<?php

// 120 条契约路由（真源 = generated/api_manifest.json；suite-hard-rules §4：
// API 面只认 shared 契约生成物，禁手写契约外面）。Task 3 路由壳：controller
// action 一律 501 占位，业务实装在后续 tag 任务逐个替换，路由面从本批起钉死。
//
// apiPrefix=''：契约路径自带 /api 前缀（manifest 1:1），留空防 /api/api 叠加
// （saas laravel 同款）。分组注释照 lab rails config/routes.rb tag 分段。
// 参数化路由统一排在同前缀静态路由之后（links 段不被 {code}/{id} 吞）。

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalculationMethodsController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\FrontendBindMetaController;
use App\Http\Controllers\InspectionCatalogController;
use App\Http\Controllers\InspectionDictionaryController;
use App\Http\Controllers\ParamInterfacesController;
use App\Http\Controllers\ReceiptsController;
use App\Http\Controllers\ReportNamesController;
use App\Http\Controllers\SamplesController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TechnicalRequirementsController;
use App\Http\Controllers\TestRecordsController;

// —— 匿名五路由（登录/自签登录/刷新 + SSO authorize/callback，ADR-0009 直连 saas IdP）——
Route::post('/api/auth/login', [AuthController::class, 'login']);
Route::post('/api/auth/native-login', [AuthController::class, 'nativeLogin']);
Route::post('/api/auth/refresh', [AuthController::class, 'refresh']);
Route::get('/api/auth/sso/authorize', [AuthController::class, 'ssoAuthorize']);
Route::post('/api/auth/sso/callback', [AuthController::class, 'ssoCallback']);

Route::middleware('jwt.guard')->group(function (): void {
    // —— auth 登录/会话（匿名 5 + 保护 5）（5 条）——
    Route::get('/api/auth/me', [AuthController::class, 'getCurrentUser']);
    Route::get('/api/auth/menus', [AuthController::class, 'getMenus']);
    Route::get('/api/auth/permissions', [AuthController::class, 'getPermissions']);
    Route::post('/api/auth/logout', [AuthController::class, 'logout']);
    Route::post('/api/auth/switch-tenant', [AuthController::class, 'switchTenant']);

    // —— calculation-methods 计算方法（复合 PK object×parameter）（5 条）——
    Route::post('/api/calculation-methods', [CalculationMethodsController::class, 'createCalculationMethod']);
    Route::get('/api/calculation-methods', [CalculationMethodsController::class, 'listCalculationMethods']);
    Route::delete('/api/calculation-methods/{inspectionObjectCode}/{inspectionParameterCode}', [CalculationMethodsController::class, 'deleteCalculationMethod']);
    Route::get('/api/calculation-methods/{inspectionObjectCode}/{inspectionParameterCode}', [CalculationMethodsController::class, 'getCalculationMethod']);
    Route::put('/api/calculation-methods/{inspectionObjectCode}/{inspectionParameterCode}', [CalculationMethodsController::class, 'updateCalculationMethod']);

    // —— contracts 委托合同（M02，tenant 隔离）（5 条）——
    Route::post('/api/contracts', [ContractsController::class, 'createContract']);
    Route::get('/api/contracts', [ContractsController::class, 'listContracts']);
    Route::delete('/api/contracts/{id}', [ContractsController::class, 'deleteContract']);
    Route::get('/api/contracts/{id}', [ContractsController::class, 'getContract']);
    Route::put('/api/contracts/{id}', [ContractsController::class, 'updateContract']);

    // —— frontend-bind-meta 前端绑定快照（内部面）（1 条）——
    Route::get('/api/_frontend-bind/snapshot', [FrontendBindMetaController::class, 'getFrontendBindSnapshot']);

    // —— inspection-catalog 字典目录（brand/grade/model/spec，code 型 PK）（16 条）——
    Route::post('/api/catalog/brands', [InspectionCatalogController::class, 'createBrand']);
    Route::post('/api/catalog/grades', [InspectionCatalogController::class, 'createGrade']);
    Route::post('/api/catalog/models', [InspectionCatalogController::class, 'createModel']);
    Route::post('/api/catalog/specs', [InspectionCatalogController::class, 'createSpec']);
    Route::get('/api/catalog/brands', [InspectionCatalogController::class, 'listBrands']);
    Route::get('/api/catalog/grades', [InspectionCatalogController::class, 'listGrades']);
    Route::get('/api/catalog/models', [InspectionCatalogController::class, 'listModels']);
    Route::get('/api/catalog/specs', [InspectionCatalogController::class, 'listSpecs']);
    Route::delete('/api/catalog/brands/{code}', [InspectionCatalogController::class, 'deleteBrand']);
    Route::delete('/api/catalog/grades/{code}', [InspectionCatalogController::class, 'deleteGrade']);
    Route::delete('/api/catalog/models/{code}', [InspectionCatalogController::class, 'deleteModel']);
    Route::delete('/api/catalog/specs/{code}', [InspectionCatalogController::class, 'deleteSpec']);
    Route::put('/api/catalog/brands/{code}', [InspectionCatalogController::class, 'updateBrand']);
    Route::put('/api/catalog/grades/{code}', [InspectionCatalogController::class, 'updateGrade']);
    Route::put('/api/catalog/models/{code}', [InspectionCatalogController::class, 'updateModel']);
    Route::put('/api/catalog/specs/{code}', [InspectionCatalogController::class, 'updateSpec']);

    // —— inspection-dictionary 检测字典（object/parameter/specialty/standard + links）（28 条）——
    Route::post('/api/inspection/objects', [InspectionDictionaryController::class, 'createObject']);
    Route::post('/api/inspection/parameters', [InspectionDictionaryController::class, 'createParameter']);
    Route::post('/api/inspection/specialties', [InspectionDictionaryController::class, 'createSpecialty']);
    Route::post('/api/inspection/standards', [InspectionDictionaryController::class, 'createStandard']);
    Route::post('/api/inspection/links/object-parameter', [InspectionDictionaryController::class, 'linkObjectParameter']);
    Route::post('/api/inspection/links/object-standard', [InspectionDictionaryController::class, 'linkObjectStandard']);
    Route::post('/api/inspection/links/specialty-object', [InspectionDictionaryController::class, 'linkSpecialtyObject']);
    Route::post('/api/inspection/links/standard-parameter', [InspectionDictionaryController::class, 'linkStandardParameter']);
    Route::get('/api/inspection/links/object-parameter', [InspectionDictionaryController::class, 'listObjectParameterLinks']);
    Route::get('/api/inspection/links/object-standard', [InspectionDictionaryController::class, 'listObjectStandardLinks']);
    Route::get('/api/inspection/objects', [InspectionDictionaryController::class, 'listObjects']);
    Route::get('/api/inspection/parameters', [InspectionDictionaryController::class, 'listParameters']);
    Route::get('/api/inspection/specialties', [InspectionDictionaryController::class, 'listSpecialties']);
    Route::get('/api/inspection/links/specialty-object', [InspectionDictionaryController::class, 'listSpecialtyObjectLinks']);
    Route::get('/api/inspection/links/standard-parameter', [InspectionDictionaryController::class, 'listStandardParameterLinks']);
    Route::get('/api/inspection/standards', [InspectionDictionaryController::class, 'listStandards']);
    Route::delete('/api/inspection/links/object-parameter', [InspectionDictionaryController::class, 'unlinkObjectParameter']);
    Route::delete('/api/inspection/links/object-standard', [InspectionDictionaryController::class, 'unlinkObjectStandard']);
    Route::delete('/api/inspection/links/specialty-object', [InspectionDictionaryController::class, 'unlinkSpecialtyObject']);
    Route::delete('/api/inspection/links/standard-parameter', [InspectionDictionaryController::class, 'unlinkStandardParameter']);
    Route::delete('/api/inspection/objects/{code}', [InspectionDictionaryController::class, 'deleteObject']);
    Route::delete('/api/inspection/parameters/{code}', [InspectionDictionaryController::class, 'deleteParameter']);
    Route::delete('/api/inspection/specialties/{code}', [InspectionDictionaryController::class, 'deleteSpecialty']);
    Route::delete('/api/inspection/standards/{code}', [InspectionDictionaryController::class, 'deleteStandard']);
    Route::put('/api/inspection/objects/{code}', [InspectionDictionaryController::class, 'updateObject']);
    Route::put('/api/inspection/parameters/{code}', [InspectionDictionaryController::class, 'updateParameter']);
    Route::put('/api/inspection/specialties/{code}', [InspectionDictionaryController::class, 'updateSpecialty']);
    Route::put('/api/inspection/standards/{code}', [InspectionDictionaryController::class, 'updateStandard']);

    // —— param-interfaces 参数接口 + links（8 条）——
    Route::post('/api/param-interfaces', [ParamInterfacesController::class, 'createParamInterface']);
    Route::post('/api/param-interfaces/links', [ParamInterfacesController::class, 'linkParamInterface']);
    Route::get('/api/param-interfaces/links', [ParamInterfacesController::class, 'listParamInterfaceLinks']);
    Route::get('/api/param-interfaces', [ParamInterfacesController::class, 'listParamInterfaces']);
    Route::delete('/api/param-interfaces/links', [ParamInterfacesController::class, 'unlinkParamInterface']);
    Route::delete('/api/param-interfaces/{code}', [ParamInterfacesController::class, 'deleteParamInterface']);
    Route::get('/api/param-interfaces/{code}', [ParamInterfacesController::class, 'getParamInterface']);
    Route::put('/api/param-interfaces/{code}', [ParamInterfacesController::class, 'updateParamInterface']);

    // —— receipts 委托接收/流程动作（M06 七态流转）（14 条）——
    Route::post('/api/receipts/approve/act', [ReceiptsController::class, 'actFlowApprove']);
    Route::post('/api/receipts/archived/act', [ReceiptsController::class, 'actFlowArchived']);
    Route::post('/api/receipts/assigning/act', [ReceiptsController::class, 'actFlowAssigning']);
    Route::post('/api/receipts/data-entry/act', [ReceiptsController::class, 'actFlowDataEntry']);
    Route::post('/api/receipts/issuance/act', [ReceiptsController::class, 'actFlowIssuance']);
    Route::post('/api/receipts/receiving/act', [ReceiptsController::class, 'actFlowReceiving']);
    Route::post('/api/receipts/review/act', [ReceiptsController::class, 'actFlowReview']);
    Route::post('/api/receipts', [ReceiptsController::class, 'createReceipt']);
    Route::get('/api/receipts', [ReceiptsController::class, 'listReceipts']);
    Route::put('/api/receipts/{id}/task', [ReceiptsController::class, 'assignTask']);
    Route::delete('/api/receipts/{id}', [ReceiptsController::class, 'deleteReceipt']);
    Route::get('/api/receipts/{id}', [ReceiptsController::class, 'getReceipt']);
    Route::get('/api/receipts/{id}/history', [ReceiptsController::class, 'getReceiptHistory']);
    Route::put('/api/receipts/{id}', [ReceiptsController::class, 'updateReceipt']);

    // —— report-names 报告名称 + links（object/parameter/standard）（14 条）——
    Route::post('/api/report-names', [ReportNamesController::class, 'createReportName']);
    Route::post('/api/report-names/links/object', [ReportNamesController::class, 'linkObjectReportName']);
    Route::post('/api/report-names/links/parameter', [ReportNamesController::class, 'linkReportNameParameter']);
    Route::post('/api/report-names/links/standard', [ReportNamesController::class, 'linkReportNameStandard']);
    Route::get('/api/report-names/links/object', [ReportNamesController::class, 'listObjectReportNameLinks']);
    Route::get('/api/report-names/links/parameter', [ReportNamesController::class, 'listReportNameParameterLinks']);
    Route::get('/api/report-names/links/standard', [ReportNamesController::class, 'listReportNameStandardLinks']);
    Route::get('/api/report-names', [ReportNamesController::class, 'listReportNames']);
    Route::delete('/api/report-names/links/object', [ReportNamesController::class, 'unlinkObjectReportName']);
    Route::delete('/api/report-names/links/parameter', [ReportNamesController::class, 'unlinkReportNameParameter']);
    Route::delete('/api/report-names/links/standard', [ReportNamesController::class, 'unlinkReportNameStandard']);
    Route::delete('/api/report-names/{code}', [ReportNamesController::class, 'deleteReportName']);
    Route::get('/api/report-names/{code}', [ReportNamesController::class, 'getReportName']);
    Route::put('/api/report-names/{code}', [ReportNamesController::class, 'updateReportName']);

    // —— samples 样品（M08，含 ext 补录）（6 条）——
    Route::post('/api/samples', [SamplesController::class, 'createSample']);
    Route::get('/api/samples', [SamplesController::class, 'listSamples']);
    Route::delete('/api/samples/{id}', [SamplesController::class, 'deleteSample']);
    Route::get('/api/samples/{id}', [SamplesController::class, 'getSample']);
    Route::put('/api/samples/{id}', [SamplesController::class, 'updateSample']);
    Route::put('/api/samples/{id}/ext', [SamplesController::class, 'updateSampleExt']);

    // —— summary 工作台统计（2 条）——
    Route::get('/api/summary/stats', [SummaryController::class, 'getDashboardStats']);
    Route::get('/api/summary', [SummaryController::class, 'getReportSummary']);

    // —— technical-requirements 技术要求（3 列复合 PK）（5 条）——
    Route::post('/api/technical-requirements', [TechnicalRequirementsController::class, 'createTechnicalRequirement']);
    Route::get('/api/technical-requirements', [TechnicalRequirementsController::class, 'listTechnicalRequirements']);
    Route::delete('/api/technical-requirements/{inspectionObjectCode}/{inspectionParameterCode}/{judgmentStandardCode}', [TechnicalRequirementsController::class, 'deleteTechnicalRequirement']);
    Route::get('/api/technical-requirements/{inspectionObjectCode}/{inspectionParameterCode}/{judgmentStandardCode}', [TechnicalRequirementsController::class, 'getTechnicalRequirement']);
    Route::put('/api/technical-requirements/{inspectionObjectCode}/{inspectionParameterCode}/{judgmentStandardCode}', [TechnicalRequirementsController::class, 'updateTechnicalRequirement']);

    // —— test-records 检测记录（M09，含 verdict 裁定）（6 条）——
    Route::post('/api/test-records', [TestRecordsController::class, 'createTestRecord']);
    Route::get('/api/test-records', [TestRecordsController::class, 'listTestRecords']);
    Route::delete('/api/test-records/{id}', [TestRecordsController::class, 'deleteTestRecord']);
    Route::get('/api/test-records/{id}', [TestRecordsController::class, 'getTestRecord']);
    Route::patch('/api/test-records/{id}/verdict', [TestRecordsController::class, 'setVerdict']);
    Route::put('/api/test-records/{id}', [TestRecordsController::class, 'updateTestRecord']);
});
