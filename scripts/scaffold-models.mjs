// scripts/scaffold-models.mjs — information_schema 反推生成 Eloquent 桩（DB-First，家族 ADR-0025 口径）
//
// 设计（移植 saas-identity-platform-laravel 同名件，2026-10-20 lab laravel 全量复刻批；
// 镜像 springboot scripts/scaffold-entities.mjs 同构）：
// - 直接读 information_schema + pg_catalog，emit PHP Eloquent 桩到
//   app/Models/Generated/<Class>.php（表名去复数 + PascalCase：
//   inspection_specialty_objects → InspectionSpecialtyObject，对齐 rails 模型名）
// - 简化版输出：$table / $primaryKey=<实际列名> / $incrementing=false / $keyType='string'
//   / $fillable 全列 / $casts（PG 类型 → Laravel cast 名，未映射类型 throw 不静默）
// - PK 三形：单列 PK（id 或 code）→ protected $primaryKey = '<实际列名>'（禁止硬编码
//   'id'）；复合 PK → $primaryKey=null + 类注释键列组（Eloquent 无原生复合主键）
// - 手写业务逻辑（常量 / 关系 / 作用域）通过 extends Generated\<Model> 叠加在
//   app/Models/<Model>.php——禁止把业务写进本生成物
// - 确定性输出（L4.codegen.idempotent strict 复跑字节比对）：表按表名排序、
//   列按 ordinal_position、无时间戳、显式 LF（Windows 下也不出 CRLF）
//
// 用法：
//   DATABASE_URL=postgresql://... bash scripts/scaffold-models.sh
//
// 前提：本仓禁 migrate/seed（DB-First：schema SSOT = shared drizzle public.*，
// 本脚本只读 information_schema）；连接目标必须是本族 _dev 库。

import { createRequire } from "node:module";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { writeFileSync, mkdirSync } from "node:fs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, "..");

// 借 pg driver（laravel 是 PHP 仓，没 node_modules；走 sibling nextjs，springboot 同款。
// 2026-10-20 清理批摘除 /app 容器死路径首试——本仓无容器运行形态）
const nextjsRoot = resolve(ROOT, "../lab-management-system-nextjs");
const requireFromNext = createRequire(resolve(nextjsRoot, "package.json"));
const { Client } = requireFromNext("pg");

// DATABASE_URL 必填。CLAUDE.md §2「禁止 env 默认值兜底」：连接串含 secret，
// 不允许在脚本里写字面量兜底；缺则 fail-fast 让调用者补 env
// （suite checker strict 会注入本机 _dev 库 URL——门禁侧契约常量）。
const DATABASE_URL = process.env.DATABASE_URL;
if (!DATABASE_URL) {
  console.error("[scaffold-models] FATAL: DATABASE_URL 未设（CLAUDE.md §2 禁字面量兜底）");
  console.error("[scaffold-models]        用法：DATABASE_URL='postgresql://…@127.0.0.1:54321/lab_dev' bash scripts/scaffold-models.sh");
  process.exit(2);
}

const OUTPUT_DIR = resolve(ROOT, "app/Models/Generated");

const EXCLUDE_TABLES = new Set([
  // Drizzle tracking — 不是业务表
  "__drizzle_migrations",
  // Rails tracking：lab_dev 由本族 rails 栈共用（真源 = shared src/db/schema.ts），
  // ActiveRecord 会在库里建自己的 bookkeeping 表——不是家族业务 schema，
  // laravel 不应为其生成 model。
  "schema_migrations",
  "ar_internal_metadata",
  // Flyway tracking：lab_dev 曾被 lab-springboot 栈使用，遗留 bookkeeping 表
  // （与 drizzle/rails tracking 同类，不是家族业务 schema）。
  "flyway_schema_history",
]);

// PG 类型 → Laravel $casts 名（镜像 springboot PG_TYPE_MAP 的覆盖面）。
// 未在此映射的 data_type → throw 不静默：schema 演进必须显式扩此表，禁止静默错 cast。
const PG_CAST_MAP = {
  uuid: "string",
  text: "string",
  "character varying": "string",
  integer: "int",
  smallint: "int",
  bigint: "int",
  boolean: "bool",
  "timestamp with time zone": "datetime",
  "timestamp without time zone": "datetime",
  date: "date",
  jsonb: "array",
};

// camelCase 转换：snake_case → camelCase（snakeToPascal 内部依赖）
function snakeToCamel(s) {
  return s.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
}

// PascalCase 转换：snake_case → PascalCase
function snakeToPascal(s) {
  const camel = snakeToCamel(s);
  return camel[0].toUpperCase() + camel.slice(1);
}

// 表名单→类名单数：lab 表名是 rails 惯例复数（inspection_brands），模型名是单数
// （InspectionBrand，rails app/models/ 同名同序）。lab 库现役表只涉及两形规则
// （-s 去尾 / -ies→y）；新表若规则不适用，加 TABLE_CLASS_OVERRIDES 显式映射
// （与 PG_CAST_MAP 同一 fail-loud 精神：schema 演进必须显式登记，禁静默猜）。
const TABLE_CLASS_OVERRIDES = new Map([]);

function dePlural(s) {
  if (s.endsWith("ies")) return `${s.slice(0, -3)}y`;
  if (s.endsWith("s")) return s.slice(0, -1);
  return s;
}

function tableToClass(t) {
  const singular = TABLE_CLASS_OVERRIDES.get(t) ?? dePlural(t);
  return snakeToPascal(singular);
}

function mapCast(dataType, udtName) {
  if (dataType === "ARRAY" || dataType === "USER-DEFINED") {
    throw new Error(
      `Unsupported PG type: ${dataType} (udt=${udtName})——schema 演进请显式扩 PG_CAST_MAP，禁静默`,
    );
  }
  const cast = PG_CAST_MAP[dataType];
  if (!cast) {
    throw new Error(
      `Unsupported PG type: ${dataType} (udt=${udtName})——schema 演进请显式扩 PG_CAST_MAP，禁静默`,
    );
  }
  return cast;
}

async function main() {
  const client = new Client({
    connectionString: DATABASE_URL,
    connectionTimeoutMillis: 10000,
  });
  await client.connect();
  console.log(`[scaffold-models] 连接 ${DATABASE_URL.replace(/:[^:@/]+@/, ":***@")}`);

  // 1. 读所有表（ORDER BY table_name → 输出按表名排序，字节幂等前提）
  const { rows: tables } = await client.query(`
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
    ORDER BY table_name
  `);
  const filtered = tables.filter((t) => !EXCLUDE_TABLES.has(t.table_name));
  console.log(`[scaffold-models] 发现 ${filtered.length} 张业务表（排除 tracking 表 ${tables.length - filtered.length} 张）`);

  // 2. 复合主键检测（information_schema.table_constraints，springboot 同款）
  const { rows: pkRows } = await client.query(`
    SELECT tc.table_name, kcu.column_name, kcu.ordinal_position
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu
      ON tc.constraint_name = kcu.constraint_name
     AND tc.table_schema = kcu.table_schema
    WHERE tc.table_schema = 'public'
      AND tc.constraint_type = 'PRIMARY KEY'
    ORDER BY tc.table_name, kcu.ordinal_position
  `);
  const compositePKs = new Map(); // tableName -> [columnName, ...]
  const pkColsByTable = new Map();
  for (const r of pkRows) {
    if (!pkColsByTable.has(r.table_name)) pkColsByTable.set(r.table_name, []);
    pkColsByTable.get(r.table_name).push(r.column_name);
  }
  for (const [tableName, cols] of pkColsByTable) {
    if (cols.length >= 2) compositePKs.set(tableName, cols);
  }

  mkdirSync(OUTPUT_DIR, { recursive: true });

  // 3. 逐表生成
  for (const tbl of filtered) {
    const { rows: cols } = await client.query(
      `
      SELECT column_name, data_type, udt_name, is_nullable
      FROM information_schema.columns
      WHERE table_schema = 'public' AND table_name = $1
      ORDER BY ordinal_position
    `,
      [tbl.table_name],
    );

    const className = tableToClass(tbl.table_name);
    const pkCols = compositePKs.get(tbl.table_name) ?? null;
    const isCompositePK = pkCols != null;

    // PK 三形（2026-10-20 lab laravel 全量复刻批裁定，覆盖 saas 版口径）：
    // - 单列 PK（id 或 code）→ 显式 protected $primaryKey = '<实际列名>'，
    //   禁止硬编码 'id'（lab 家族字典表 PK 普遍是 code，Eloquent 默认才假定 id）；
    // - 复合 PK → $primaryKey = null + 类注释键列组（Eloquent 无原生复合主键）。
    // 单列 PK 表才带 $incrementing/$keyType 语义；复合主键表两行皆无意义（评审 I-1）。
    const pkCol = !isCompositePK ? pkColsByTable.get(tbl.table_name)?.[0] : null;
    const incrementingBlock = isCompositePK
      ? ""
      : `
    public $incrementing = false;

    protected $keyType = 'string';
`;

    const fillable = cols.map((c) => `        '${c.column_name}',`);
    const casts = cols.map((c) => `        '${c.column_name}' => '${mapCast(c.data_type, c.udt_name)}',`);

    // 复合主键：$primaryKey = null。不带 @var 标签——父类 Model::$primaryKey
    // @var string 与 string|null 不协变（phpstan property.phpDocType 会红），置 null 本身
    // larastan L5 实测不报，无需 ignore。
    const primaryKeyBlock = isCompositePK
      ? `
    // 复合主键：${pkCols.join(" + ")}（${pkCols.length} 列联合主键，真源=information_schema）。
    // Eloquent 不支持原生复合主键——置 null 禁用单键 find() 语义，
    // 写操作一律 where 全列显式定位；手写子类禁止覆写回单键。
    protected $primaryKey = null;
`
      : `
    protected $primaryKey = '${pkCol}';
`;

    const compositeDoc = isCompositePK
      ? ` *\n * 复合主键：${pkCols.join(" + ")}（${pkCols.length} 列联合主键）——单键 find() 禁用，见 \$primaryKey 注释。\n`
      : "";

    const php = `<?php

declare(strict_types=1);

// DB-First scaffold：${tbl.table_name}（家族 DB-First 口径；schema SSOT = shared drizzle public.${tbl.table_name}，
// 本仓禁 migrate/seed）。由 scripts/scaffold-models.mjs 从 lab_dev 真库 information_schema 反推生成——
// 禁手改，L4.codegen.idempotent strict 复跑字节比对会暴露手改。手写业务逻辑（常量 / 关系 / 领域方法）
// 写在 app/Models/${className}.php extends Generated\\${className}。
//
// 字段含义见 lab-management-system-shared/src/db/schema.ts。

namespace App\\Models\\Generated;

use Illuminate\\Database\\Eloquent\\Model;

/**
 * DB-First scaffold：${tbl.table_name}。
${compositeDoc} */
class ${className} extends Model
{
    protected $table = '${tbl.table_name}';

    // Eloquent 默认 $timestamps=true 会自动 touch created_at/updated_at——lab 家族
    // 口径（镜像 rails ApplicationRecord record_timestamps=false / nowIso()）：两列是
    // TEXT 列，由应用层写 ISO-8601 UTC 串，24 表全量关自动时间戳防格式分叉。
    // 时间戳由业务代码显式写列（DB-First：created_at/updated_at 是普通列）。
    public $timestamps = false;
${incrementingBlock}${primaryKeyBlock}
    /** @var list<string> */
    protected $fillable = [
${fillable.join("\n")}
    ];

    /** @var array<string, string> */
    protected $casts = [
${casts.join("\n")}
    ];
}
`;

    const outPath = resolve(OUTPUT_DIR, `${className}.php`);
    writeFileSync(outPath, php, "utf8");
    console.log(
      `[scaffold-models]   → ${tbl.table_name} → Models/Generated/${className}.php (${cols.length} 字段${isCompositePK ? ", composite PK " + pkCols.join("+") : ", PK " + pkCol})`,
    );
  }

  await client.end();
  console.log(`[scaffold-models] OK — ${filtered.length} 个 Eloquent 桩已生成到 ${OUTPUT_DIR}`);
}

main().catch((err) => {
  console.error("[scaffold-models] FATAL:", err.message);
  console.error(err.stack);
  process.exit(1);
});
