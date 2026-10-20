<?php

namespace Tests\Contracts;

use App\Models\Contract;
use App\Models\InspectionBrand;
use App\Models\InspectionCalculationMethod;
use App\Models\InspectionGrade;
use App\Models\InspectionModel;
use App\Models\InspectionObject;
use App\Models\InspectionObjectParameter;
use App\Models\InspectionObjectReportName;
use App\Models\InspectionObjectStandard;
use App\Models\InspectionParameter;
use App\Models\InspectionParamInterface;
use App\Models\InspectionParamInterfaceLink;
use App\Models\InspectionReportName;
use App\Models\InspectionReportNameParameter;
use App\Models\InspectionReportNameStandard;
use App\Models\InspectionSpec;
use App\Models\InspectionSpecialty;
use App\Models\InspectionSpecialtyObject;
use App\Models\InspectionStandard;
use App\Models\InspectionStandardParameter;
use App\Models\InspectionTechnicalRequirement;
use App\Models\Sample;
use App\Models\SampleReceipt;
use App\Models\TestRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DB-First parity（ADR-0025 的 Laravel 等价强制；镜像 rails
 * test/contracts/schema_parity_test.rb 三用例）：模型 schema-less（列运行时从
 * information_schema 读），漂移风险收敛为「table_name/主键拼错」，本测试连测试库
 * （APP_ENV=test → .env.test → lab_test）reflect 对账，拼错即红。
 */
final class SchemaParityTest extends TestCase
{
    /** id 主键业务表（家族 id 前缀约定：C-/R-/S-/TR-<uuid>，TEXT 列） */
    private const UUID_PK_MODELS = [
        Contract::class,
        SampleReceipt::class,
        Sample::class,
        TestRecord::class,
    ];

    /** code 主键字典表 */
    private const CODE_PK_MODELS = [
        InspectionBrand::class,
        InspectionGrade::class,
        InspectionModel::class,
        InspectionSpec::class,
        InspectionSpecialty::class,
        InspectionObject::class,
        InspectionParameter::class,
        InspectionStandard::class,
        InspectionReportName::class,
        InspectionParamInterface::class,
    ];

    /** @var array<class-string<Model>, list<string>> 复合主键 junction 表（无 id 列，shared 复合 pk；真源 = information_schema 约束） */
    private const COMPOSITE_PK_MODELS = [
        InspectionCalculationMethod::class => ['inspection_object_code', 'inspection_parameter_code'],
        InspectionSpecialtyObject::class => ['inspection_specialty_code', 'inspection_object_code'],
        InspectionObjectParameter::class => ['inspection_object_code', 'inspection_parameter_code'],
        InspectionObjectStandard::class => ['inspection_object_code', 'inspection_standard_code', 'role'],
        InspectionStandardParameter::class => ['inspection_standard_code', 'inspection_parameter_code'],
        InspectionObjectReportName::class => ['inspection_object_code', 'report_name_code'],
        InspectionReportNameParameter::class => ['report_name_code', 'inspection_parameter_code'],
        InspectionReportNameStandard::class => ['report_name_code', 'inspection_standard_code', 'role'],
        InspectionParamInterfaceLink::class => ['inspection_parameter_code', 'param_interface_code'],
        InspectionTechnicalRequirement::class => ['inspection_object_code', 'inspection_parameter_code', 'judgment_standard_code'],
    ];

    #[Test]
    public function every_model_maps_to_a_real_table_with_the_declared_pk_columns(): void
    {
        /** @var class-string<Model> $modelClass */
        foreach (self::allModels() as $modelClass) {
            $model = new $modelClass;
            $table = $model->getTable();

            self::assertTrue(
                Schema::hasTable($table),
                "{$modelClass} -> {$table} does not exist in test DB"
            );

            $pkColumns = self::COMPOSITE_PK_MODELS[$modelClass]
                ?? [$model->getKeyName()];
            foreach ($pkColumns as $column) {
                self::assertTrue(
                    Schema::hasColumn($table, $column),
                    "{$modelClass} missing pk column '{$column}'"
                );
            }
        }
    }

    #[Test]
    public function business_id_pk_columns_are_text(): void
    {
        // lab id 前缀约定：C-/R-/S-/TR-<uuid>（家族 id 前缀字符串，非 saas 的 uuid 型）。
        /** @var class-string<Model> $modelClass */
        foreach (self::UUID_PK_MODELS as $modelClass) {
            $table = (new $modelClass)->getTable();

            $row = DB::selectOne(
                "select data_type from information_schema.columns
                 where table_schema = 'public' and table_name = ? and column_name = 'id'",
                [$table]
            );

            self::assertNotNull($row, "{$modelClass}.{$table}.id column not found");
            self::assertSame(
                'text',
                $row->data_type,
                "{$modelClass} pk column 'id' should be text（家族 id 前缀字符串，非 saas 的 uuid 型）"
            );
        }
    }

    #[Test]
    public function models_do_not_declare_a_laravel_schema(): void
    {
        // DB-First 泄漏门：lab-laravel 无 database/migrations（schema SSOT = shared src/db/schema.ts）。
        $migrations = glob(database_path('migrations').'/*.php');

        self::assertSame(
            [],
            $migrations === false ? [] : $migrations,
            'lab-laravel 无 database/migrations（schema SSOT = shared src/db/schema.ts，禁 migrate/seed）'
        );
    }

    /** @return list<class-string<Model>> */
    private static function allModels(): array
    {
        return [
            ...self::UUID_PK_MODELS,
            ...self::CODE_PK_MODELS,
            ...array_keys(self::COMPOSITE_PK_MODELS),
        ];
    }
}
