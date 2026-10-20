<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Throwable;

// 报告流程状态机 M03.F01-F08，7 阶段全 act 模式（lab-springboot ReportFlowService
// 镜像 → rails Receipts::ReportFlowService 同构移植，2026-09-17 收敛版：
// POST /receipts/<stage>/act，body.action={submit|return|withdraw}）。
//
// action 范围因 stage 而异：
// - receiving/assigning/data-entry：{submit, return, withdraw}
// - review/approve/issuance：{submit, return}
// - archived：仅 submit（终态自转移，写 history 当 audit，语义按 submit 走 5.69）
// WITHDRAW 仅在 receiving 阶段自转移。per-id try/catch：单条失败出 err 结果不整批 5xx。
final class ReportFlowService
{
    private const SUBMIT_NEXT = [
        'receiving' => 'task_assignment', 'task_assignment' => 'data_entry',
        'data_entry' => 'review', 'review' => 'approval', 'approval' => 'issuance',
        'issuance' => 'archived',
    ];

    private const RETURN_PREV = [
        'task_assignment' => 'receiving', 'data_entry' => 'task_assignment',
        'review' => 'data_entry', 'approval' => 'review', 'issuance' => 'approval',
        'archived' => 'issuance',
    ];

    public function __construct(private readonly SampleReceiptService $receipts) {}

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    public function act(string $stage, array $body): array
    {
        $this->requireOperator($body);
        $operator = (string) $body['operator'];
        $rawReason = $body['reason'] ?? null;
        $reason = is_string($rawReason) ? $rawReason : null;
        $rawAction = $body['action'] ?? null;
        $action = is_string($rawAction) ? $rawAction : null;
        $rawIds = $body['ids'] ?? null;
        $ids = is_array($rawIds) ? $rawIds : ($rawIds === null ? [] : [$rawIds]);

        $results = [];
        foreach ($ids as $rawId) {
            $id = (string) $rawId;
            $results[] = $stage === 'archived'
                ? $this->actArchivedOne($id, $action, $operator, $reason)
                : $this->actStageOne($stage, $id, $action, $operator, $reason);
        }

        return $results;
    }

    // ==== private 段（rails 同位） ====

    // 5.75 operator 契约必填（SSOT = lab-nextjs act-route.ts:39-44）：缺失与空串都 400，
    // 校验先于 per-id 循环（整批拒）——IAE 由基座映射 400。
    private function requireOperator(array $body): void
    {
        $operator = $body['operator'] ?? null;
        if (! is_string($operator) || $operator === '') {
            throw new InvalidArgumentException('operator is required');
        }
    }

    private function actStageOne(
        string $stage,
        string $id,
        ?string $action,
        string $operator,
        ?string $reason
    ): array {
        try {
            $current = (string) ($this->receipts->get($id)['flow_status'] ?? '');
            if ($current !== $stage) {
                return $this->err($id, "Stage mismatch: requires {$stage} but is {$current}");
            }

            $target = $this->targetOf($current, $action);
            if ($target === null) {
                return $this->err($id, "Invalid transition from {$current} with {$action}");
            }

            $this->receipts->transitionTo($id, $current, $target, (string) $action, $operator, $reason);

            return $this->ok($id, $target);
        } catch (ModelNotFoundException) {
            return $this->err($id, "Receipt not found: {$id}");
        } catch (Throwable $e) {
            // rails rescue StandardError => e; err(id, e.message) 同形（含 transition_to
            // 的 IAE「Receipt <id> not in expected stage …」）
            return $this->err($id, $e->getMessage());
        }
    }

    // archived 终态：仅 submit；自转移写 audit（reason 缺省 "archived: post-archive audit"，
    // 语义按 submit —— lastSubmittedBy 写操作人）
    private function actArchivedOne(
        string $id,
        ?string $action,
        string $operator,
        ?string $reason
    ): array {
        try {
            $current = (string) ($this->receipts->get($id)['flow_status'] ?? '');
            if ($current !== 'archived') {
                return $this->err($id, "Stage mismatch: requires archived but is {$current}");
            }
            if ($action !== 'submit') {
                return $this->err($id, "Action not allowed: archived accepts only submit but got {$action}");
            }

            $this->receipts->transitionTo(
                $id,
                'archived',
                'archived',
                'submit',
                $operator,
                $reason ?? 'archived: post-archive audit'
            );

            return $this->ok($id, 'archived');
        } catch (ModelNotFoundException) {
            return $this->err($id, "Receipt not found: {$id}");
        } catch (Throwable $e) {
            return $this->err($id, $e->getMessage());
        }
    }

    private function targetOf(string $current, ?string $action): ?string
    {
        return match ($action) {
            'submit' => self::SUBMIT_NEXT[$current] ?? null,
            'return' => self::RETURN_PREV[$current] ?? null,
            'withdraw' => $current === 'receiving' ? 'receiving' : null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function ok(string $id, string $flowStatus): array
    {
        return ['id' => $id, 'ok' => true, 'flow_status' => $flowStatus];
    }

    /**
     * @return array<string, mixed>
     */
    private function err(string $id, string $message): array
    {
        return ['id' => $id, 'ok' => false, 'message' => $message];
    }
}
