<div class="mt-4">
    <h5>💰 Caisse des agents (
        @switch($filter)
            @case($filter === 'day')
                Aujourd'hui
                @break
            @case($filter === 'week')
                Cette semaine
                @break
            @case($filter === 'month')
                Ce mois
                @break
            @case($filter === 'year')
                Cette année
                @break
            @case($filter === 'custom')
                {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
            @break

            @default

        @endswitch
    )</h5>

    <div class="row g-4">
        @foreach ($users as $agent)
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0">Agent : {{ $agent->name }} {{ $agent->postnom }}</h6>
                        <div class="dropdown">
                            <button class="btn p-0" type="button" id="dropdown-{{ $agent->id }}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown-{{ $agent->id }}">
                                <a class="dropdown-item" href="javascript:void(0);" wire:click='showTransactions({{ $agent->id }}, "day")'>Aujourd'hui</a>
                                <a class="dropdown-item" href="javascript:void(0);" wire:click='showTransactions({{ $agent->id }}, "week")'>Cette semaine</a>
                                <a class="dropdown-item" href="javascript:void(0);" wire:click='showTransactions({{ $agent->id }}, "month")'>Ce mois</a>
                                <a class="dropdown-item" href="javascript:void(0);" wire:click='showTransactions({{ $agent->id }}, "year")'>Cette année</a>
                                <a class="dropdown-item" href="javascript:void(0);" wire:click='showTransactions({{ $agent->id }}, "custom")'>Intervalle</a>
                            </div>
                            @if ($filter === 'custom')
                                <div class="">
                                    <input type="date" class="form-control" wire:model="startDate">
                                </div>
                                <div class="">
                                    <input type="date" class="form-control" wire:model="endDate">
                                </div>
                                <div class="">
                                    <button class="btn btn-primary w-100" wire:click="$refresh">Filtrer</button>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card-body">
                        @if (!empty($balances[$agent->id]))
                            <ul class="list-unstyled mb-0">
                                @foreach ($balances[$agent->id] as $currency => $balance)
                                    <li class="d-flex justify-content-between mb-2">
                                        <span>{{ $currency }}</span>
                                        <strong>{{ number_format($balance, 2) }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted">Aucune transaction pour cette période</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Historique -->
    @if ($isShowTransaction)
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="m-0">Historique des transactions</h5>
                <small class="text-muted">{{ $periodLabel }}</small>
            </div>

            <a href="{{ route('agent.transactions.export', ['userId' => $user_id, 'filter' => $filter, 'startDate' => $startDate, 'endDate' => $endDate]) }}"
               class="btn btn-sm btn-danger">
                <i class="bx bx-file me-1"></i> Exporter PDF
            </a>
        </div>
        <div class="card-body">
                @forelse ($transactions as $t)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div>
                            <strong>{{ ucfirst($t->type) }}</strong><br>
                            <small>{{ $t->currency }} - {{ number_format($t->amount, 2) }}</small><br>
                            <small class="text-muted">{{ $t->description }}</small>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark">
                                {{ $t->created_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mt-3">Aucune transaction trouvée.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
