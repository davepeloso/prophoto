<x-filament-panels::page>
    <style>
        .ppm-wrap {
            display: grid;
            gap: 1rem;
        }

        .ppm-note,
        .ppm-table-wrap,
        .ppm-stat {
            border: 1px solid #2a2f3a;
            border-radius: 12px;
            background: #11161f;
            color: #e5e7eb;
        }

        .ppm-note {
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.45;
            color: #cbd5e1;
        }

        .ppm-table-wrap {
            overflow: auto;
        }

        .ppm-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
            font-size: 13px;
            line-height: 1.35;
        }

        .ppm-table th,
        .ppm-table td {
            border-bottom: 1px solid #2a2f3a;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .ppm-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #1a2230;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
        }

        .ppm-table thead th.role {
            text-align: center;
            min-width: 130px;
        }

        .ppm-category-row td {
            background: #151d2a;
            color: #e2e8f0;
            border-top: 1px solid #334155;
            border-bottom: 1px solid #334155;
        }

        .ppm-category-head {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            align-items: center;
            justify-content: space-between;
        }

        .ppm-category-title {
            font-weight: 700;
        }

        .ppm-category-count {
            color: #94a3b8;
            font-size: 11px;
            margin-left: 6px;
        }

        .ppm-bulk {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
            font-size: 11px;
            color: #94a3b8;
        }

        .ppm-bulk button {
            border: 0;
            background: transparent;
            padding: 0;
            font-weight: 700;
            cursor: pointer;
        }

        .ppm-bulk .all {
            color: #34d399;
        }

        .ppm-bulk .none {
            color: #fb7185;
        }

        .ppm-perm-label {
            display: block;
            font-weight: 600;
            color: #f1f5f9;
        }

        .ppm-perm-key {
            display: block;
            margin-top: 3px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 11px;
            color: #94a3b8;
        }

        .ppm-center {
            text-align: center;
            vertical-align: middle;
        }

        .ppm-toggle {
            width: 34px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
        }

        .ppm-toggle.on {
            background: #065f46;
            border-color: #10b981;
            color: #d1fae5;
        }

        .ppm-toggle.off {
            background: #1f2937;
            border-color: #4b5563;
            color: #9ca3af;
        }

        .ppm-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .ppm-stat {
            padding: 12px;
        }

        .ppm-stat-name {
            color: #94a3b8;
            font-size: 12px;
        }

        .ppm-stat-value {
            margin-top: 2px;
            font-size: 22px;
            font-weight: 700;
            color: #f8fafc;
        }

        .ppm-stat-sub {
            color: #94a3b8;
            font-size: 11px;
        }

        .ppm-progress {
            margin-top: 8px;
            height: 8px;
            border-radius: 999px;
            background: #263043;
            overflow: hidden;
        }

        .ppm-progress > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #10b981, #3b82f6);
        }
    </style>

    <div class="ppm-wrap">
        <div class="ppm-note">
            Matrix is the fast bulk editor across all roles. Click a cell to toggle one permission, or use ALL/NONE in a category row.
        </div>

        <div class="ppm-table-wrap">
            <table class="ppm-table">
                <thead>
                    <tr>
                        <th style="min-width: 280px;">Permission</th>
                        @foreach($roles as $roleId => $roleName)
                            <th class="role">{{ str_replace('_', ' ', $roleName) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category => $categoryPermissions)
                        <tr class="ppm-category-row">
                            <td colspan="{{ count($roles) + 1 }}">
                                <div class="ppm-category-head">
                                    <div class="ppm-category-title">
                                        {{ $category }}
                                        <span class="ppm-category-count">({{ count($categoryPermissions) }} permissions)</span>
                                    </div>
                                    <div class="ppm-bulk">
                                        @foreach($roles as $roleId => $roleName)
                                            <span>
                                                {{ $roleName }}
                                                <button
                                                    class="all"
                                                    wire:click="grantAllInCategory({{ $roleId }}, '{{ $category }}')"
                                                    wire:loading.attr="disabled"
                                                >ALL</button>
                                                /
                                                <button
                                                    class="none"
                                                    wire:click="revokeAllInCategory({{ $roleId }}, '{{ $category }}')"
                                                    wire:loading.attr="disabled"
                                                >NONE</button>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>

                        @foreach($categoryPermissions as $permission)
                            <tr>
                                <td>
                                    <span class="ppm-perm-label">{{ $permission['description'] }}</span>
                                    <span class="ppm-perm-key">{{ $permission['name'] }}</span>
                                </td>
                                @foreach($roles as $roleId => $roleName)
                                    @php
                                        $enabled = in_array($permission['id'], $matrix[$roleId] ?? [], true);
                                    @endphp
                                    <td class="ppm-center">
                                        <button
                                            wire:click="togglePermission({{ $roleId }}, {{ $permission['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50"
                                            class="ppm-toggle {{ $enabled ? 'on' : 'off' }}"
                                            title="{{ $enabled ? 'Click to revoke' : 'Click to grant' }}"
                                        >
                                            {{ $enabled ? '✓' : '−' }}
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="{{ count($roles) + 1 }}" style="padding: 18px; color: #94a3b8; text-align: center;">
                                No permissions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ppm-stats">
            @foreach($roles as $roleId => $roleName)
                @php
                    $permCount = count($matrix[$roleId] ?? []);
                    $totalPerms = array_sum(array_map('count', $categories));
                    $percentage = $totalPerms > 0 ? round(($permCount / $totalPerms) * 100) : 0;
                @endphp
                <div class="ppm-stat">
                    <div class="ppm-stat-name">{{ str_replace('_', ' ', $roleName) }}</div>
                    <div class="ppm-stat-value">{{ $permCount }}</div>
                    <div class="ppm-stat-sub">of {{ $totalPerms }} permissions ({{ $percentage }}%)</div>
                    <div class="ppm-progress">
                        <span style="width: {{ $percentage }}%;"></span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
