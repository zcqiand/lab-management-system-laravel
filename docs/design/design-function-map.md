# 设计与功能对齐 — 实验室管理系统 Rails 后端

> 人填、人评审。机器只检查功能 ID 存在性。
> 回答一个问题：**这个功能子项，落到哪段代码、哪张表、哪个权限码上？**
> 答不上来的行，说明设计没做完，别开工。

## 映射表

| 功能子项 ID | 页面/组件 | 接口 | 数据表 | 权限码 | 设计稿 | 状态 |
|---|---|---|---|---|---|---|
| M05.F01.I03 | Summary::SummaryService#dashboard_stats（today_test_count/qualified_rate_by_material/report_output_by_status 段） | GET /api/summary/stats | sample_receipts + inspection_report_names（码表预载 summary_name 关键词映射） | M05.F01.I03 | REQ-2026-022，镜像 springboot 同名行 | 已上线 |
| M05.F01.I04 | Summary::SummaryService#dashboard_stats（funnel_by_stage 段） | GET /api/summary/stats | sample_receipts（flow_status + report_code 六段分桶） | M05.F01.I04 | REQ-2026-022，镜像 springboot 同名行 | 已上线 |
| | | | | | | |

## 约定

1. **权限码 = 功能子项 ID。** 前端按钮的权限判断直接写 ID。
2. 一个接口服务多个子项时，多行重复写。不要为表好看而合并 —— 合并后看不清接口还有没有别的调用方。
3. 状态列必须与功能清单一致。不一致以功能清单为准。

## 评审时问这三个问题

1. 有没有子项没有权限码？→ 那它就是任何人都能点的按钮
2. 有没有一张表被三个以上模块直接写入？→ 边界破了
3. 「开发中」的行里接口和表填了吗？→ 没填就是还在纸上，别报进度
