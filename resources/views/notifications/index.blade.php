@extends('layouts.app')

@section('content')
<div style="padding: 24px; max-width: 1400px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
        <h1 style="font-size: 28px; font-weight: 700; color: #111827; margin: 0; display:flex; align-items:center; gap:10px;">
            <i data-lucide="bar-chart-3" style="width:28px; height:28px; color:#3b82f6;"></i>
            Известия и анализи
        </h1>
    </div>

    {{-- Summary карти с икони --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 32px;">
        <div style="background: linear-gradient(135deg,#dbeafe,#bfdbfe); padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(255,255,255,0.5); padding:12px; border-radius:10px;">
                <i data-lucide="bell" style="width:28px; height:28px; color:#1e40af;"></i>
            </div>
            <div>
                <div style="font-size: 12px; color:#1e40af; text-transform: uppercase; font-weight: 600;">Общо</div>
                <div style="font-size: 32px; font-weight: 700; color:#1e3a8a;">{{ $summary['total'] }}</div>
            </div>
        </div>
        <div style="background: linear-gradient(135deg,#dcfce7,#bbf7d0); padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(255,255,255,0.5); padding:12px; border-radius:10px;">
                <i data-lucide="trending-down" style="width:28px; height:28px; color:#15803d;"></i>
            </div>
            <div>
                <div style="font-size: 12px; color:#15803d; text-transform: uppercase; font-weight: 600;">Поевтиняли</div>
                <div style="font-size: 32px; font-weight: 700; color:#14532d;">{{ $summary['cheaper'] }}</div>
            </div>
        </div>
        <div style="background: linear-gradient(135deg,#fee2e2,#fecaca); padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(255,255,255,0.5); padding:12px; border-radius:10px;">
                <i data-lucide="trending-up" style="width:28px; height:28px; color:#991b1b;"></i>
            </div>
            <div>
                <div style="font-size: 12px; color:#991b1b; text-transform: uppercase; font-weight: 600;">Поскъпнали</div>
                <div style="font-size: 32px; font-weight: 700; color:#7f1d1d;">{{ $summary['pricier'] }}</div>
            </div>
        </div>
        <div style="background: linear-gradient(135deg,#fef3c7,#fde68a); padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display:flex; align-items:center; gap:16px;">
            <div style="background:rgba(255,255,255,0.5); padding:12px; border-radius:10px;">
                <i data-lucide="clock" style="width:28px; height:28px; color:#92400e;"></i>
            </div>
            <div>
                <div style="font-size: 12px; color:#92400e; text-transform: uppercase; font-weight: 600;">Последни 24ч</div>
                <div style="font-size: 32px; font-weight: 700; color:#78350f;">{{ $summary['last_24h'] }}</div>
            </div>
        </div>
    </div>

    {{-- Филтри с големи бутони --}}
    <form method="GET" style="background:white; padding:20px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:24px;">
        <div style="display:grid; grid-template-columns: 1fr 1fr 2fr; gap:12px; margin-bottom:16px;">
            <div>
                <label style="font-size:12px; color:#6b7280; font-weight:600; display:block; margin-bottom:6px;">Магазин</label>
                <select name="store_id" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:15px;">
                    <option value="">Всички</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ ($filters['store_id'] ?? '') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:12px; color:#6b7280; font-weight:600; display:block; margin-bottom:6px;">Период</label>
                <select name="period" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:15px;">
                    <option value="">Всички</option>
                    <option value="today" {{ ($filters['period'] ?? '') === 'today' ? 'selected' : '' }}>Днес</option>
                    <option value="week" {{ ($filters['period'] ?? '') === 'week' ? 'selected' : '' }}>Последните 7 дни</option>
                    <option value="month" {{ ($filters['period'] ?? '') === 'month' ? 'selected' : '' }}>Последните 30 дни</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px; color:#6b7280; font-weight:600; display:block; margin-bottom:6px;">Търсене по продукт</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Име на продукт..." style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:15px;">
            </div>
        </div>
        <div style="display:flex; gap:12px;">
            <button type="submit" style="flex:1; padding:14px; background:#3b82f6; color:white; border:none; border-radius:8px; font-weight:600; font-size:15px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i data-lucide="filter" style="width:18px; height:18px;"></i>
                Филтрирай
            </button>
            <a href="{{ route('notifications.all') }}" style="flex:1; padding:14px; background:#f3f4f6; color:#374151; text-decoration:none; border-radius:8px; text-align:center; font-weight:600; font-size:15px; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i data-lucide="x" style="width:18px; height:18px;"></i>
                Изчисти
            </a>
        </div>
    </form>

    {{-- Графика по дни --}}
    @if(array_sum($summary['by_day']) > 0)
    <div style="background:white; padding:20px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:24px;">
        <h3 style="margin:0 0 16px 0; font-size:18px; color:#111827; display:flex; align-items:center; gap:8px;">
            <i data-lucide="line-chart" style="width:20px; height:20px; color:#3b82f6;"></i>
            Известия по дни
        </h3>
        <div style="display:flex; gap:8px; align-items:end; height:120px;">
            @php $maxDay = max(1, max($summary['by_day'])); @endphp
            @foreach($summary['by_day'] as $day => $count)
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px;">
                    <div style="font-size:12px; font-weight:600; color:#4b5563;">{{ $count }}</div>
                    <div style="width:100%; height:{{ ($count / $maxDay) * 80 }}px; background:linear-gradient(180deg, #60a5fa, #3b82f6); border-radius:6px 6px 0 0; min-height:4px;"></div>
                    <div style="font-size:11px; color:#9ca3af;">{{ $day }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- По магазин --}}
    @if(count($summary['by_store']) > 0)
    <div style="background:white; padding:20px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:24px;">
        <h3 style="margin:0 0 16px 0; font-size:18px; color:#111827; display:flex; align-items:center; gap:8px;">
            <i data-lucide="store" style="width:20px; height:20px; color:#3b82f6;"></i>
            По магазин
        </h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
            @foreach($summary['by_store'] as $storeName => $data)
                <div style="border:1px solid #e5e7eb; border-radius:10px; padding:14px; background:#fafafa;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                        <i data-lucide="shopping-bag" style="width:16px; height:16px; color:#6b7280;"></i>
                        <div style="font-weight:700; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $storeName }}</div>
                    </div>
                    <div style="font-size:28px; font-weight:700; color:#3b82f6; margin-bottom:4px;">{{ $data['count'] }}</div>
                    @if($data['avg'] !== null)
                        <div style="font-size:13px; color:{{ $data['avg'] < 0 ? '#15803d' : ($data['avg'] > 0 ? '#991b1b' : '#6b7280') }};">
                            Средна: {{ $data['avg'] > 0 ? '+' : '' }}{{ $data['avg'] }}%
                        </div>
                    @endif
                    <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                        <span style="color:#15803d;">▼ {{ $data['cheaper'] }}</span> ·
                        <span style="color:#991b1b;">▲ {{ $data['pricier'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Top 5 промени --}}
    @if($summary['top']->count() > 0)
    <div style="background:white; padding:20px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:24px;">
        <h3 style="margin:0 0 16px 0; font-size:18px; color:#111827; display:flex; align-items:center; gap:8px;">
            <i data-lucide="flame" style="width:20px; height:20px; color:#f97316;"></i>
            Най-голяма промяна (Top 5)
        </h3>
        <div>
            @foreach($summary['top'] as $n)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f3f4f6;">
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:500; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $n->product->name ?? 'Продукт' }}</div>
                        <div style="font-size:12px; color:#9ca3af;">{{ $n->store->name ?? '-' }} · {{ $n->old_price }}€ → {{ $n->new_price }}€</div>
                    </div>
                    <div style="font-weight:700; padding:4px 12px; border-radius:20px; {{ $n->price_change_percent < 0 ? 'background:#dcfce7; color:#15803d;' : 'background:#fee2e2; color:#991b1b;' }}">
                        {{ $n->price_change_percent > 0 ? '+' : '' }}{{ $n->price_change_percent }}%
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Списък всички с pagination --}}
    <div style="background:white; padding:20px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <h3 style="margin:0 0 16px 0; font-size:18px; color:#111827; display:flex; align-items:center; gap:8px;">
            <i data-lucide="clipboard-list" style="width:20px; height:20px; color:#3b82f6;"></i>
            Всички известия ({{ $notifications->total() }})
        </h3>
        <div>
            @forelse($notifications as $n)
                <div style="padding:14px; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:start; gap:16px;">
                    <div style="flex:1;">
                        <div style="color:#111827; line-height:1.5;">{{ $n->message }}</div>
                        <div style="font-size:12px; color:#9ca3af; margin-top:4px;">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                    @if($n->price_change_percent !== null)
                        <div style="flex-shrink:0; font-weight:600; padding:3px 10px; border-radius:12px; font-size:13px; {{ $n->price_change_percent < 0 ? 'background:#dcfce7; color:#15803d;' : 'background:#fee2e2; color:#991b1b;' }}">
                            {{ $n->price_change_percent < 0 ? '▼' : '▲' }} {{ abs($n->price_change_percent) }}%
                        </div>
                    @endif
                </div>
            @empty
                <div style="padding:40px; text-align:center; color:#9ca3af;">Няма известия</div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div style="margin-top:20px;">
            {{ $notifications->appends($filters)->links() }}
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection
