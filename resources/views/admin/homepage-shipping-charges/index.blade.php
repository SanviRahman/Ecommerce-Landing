@extends('adminlte::page')

@section('title', 'Shipping Charge for Homepage')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap p49-titlebar">
        <div>
            <h1 class="mb-1">Shipping Charge for Homepage</h1>
            <p class="text-muted mb-0">Manage delivery areas and charges for the main NextGenAIBD Storefront Checkout.</p>
        </div>
        <a href="{{ route('admin.storefront.home.index') }}" class="btn btn-outline-secondary mt-2 mt-md-0">
            <i class="fas fa-home mr-1" aria-hidden="true"></i> Homepage Builder
        </a>
    </div>
@stop

@section('content')
    <div class="p49-home-shipping pb-4">
        @if(session('success'))
            <div class="alert alert-success" role="status"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i> {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Please review:</strong>
                <ul class="mb-0 mt-1 pl-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="p49-hero mb-4">
            <div class="p49-hero-symbol" aria-hidden="true"><i class="fas fa-shipping-fast"></i></div>
            <div class="flex-grow-1">
                <div class="p49-eyebrow">STOREFRONT DELIVERY SETTINGS</div>
                <h2>Set shipping charges for your Homepage</h2>
                <p class="mb-0">Customer selects an active delivery area at Checkout. The charge is calculated on the server. Campaign delivery charges stay independent.</p>
            </div>
        </div>

        <div class="row p49-metrics">
            <div class="col-sm-4 mb-3">
                <div class="p49-metric"><span class="p49-metric-icon text-success"><i class="fas fa-check-circle" aria-hidden="true"></i></span>
                    <div><strong>{{ $activeCount }}</strong><span>Active delivery areas</span></div>
                </div>
            </div>
            <div class="col-sm-4 mb-3">
                <div class="p49-metric"><span class="p49-metric-icon text-secondary"><i class="fas fa-pause-circle" aria-hidden="true"></i></span>
                    <div><strong>{{ $inactiveCount }}</strong><span>Inactive areas</span></div>
                </div>
            </div>
            <div class="col-sm-4 mb-3">
                <div class="p49-metric"><span class="p49-metric-icon {{ $activeCount > 0 ? 'text-success' : 'text-warning' }}"><i class="fas fa-{{ $activeCount > 0 ? 'shield-alt' : 'exclamation-triangle' }}" aria-hidden="true"></i></span>
                    <div><strong class="p49-metric-status">{{ $activeCount > 0 ? 'Configured' : 'Action required' }}</strong><span>Homepage shipping availability</span></div>
                </div>
            </div>
        </div>

        @if($activeCount === 0)
            <div class="alert alert-warning p49-notice" role="status">
                <i class="fas fa-exclamation-circle mr-1" aria-hidden="true"></i>
                There is no active Homepage delivery area. Add an area and enable it to satisfy the shipping portion of Checkout Readiness.
            </div>
        @endif
        <div class="p49-note mb-4">
            <i class="fas fa-info-circle mr-2" aria-hidden="true"></i>
            <span><strong>Scope protection:</strong> Only shipping rows without a Campaign are managed here. Campaign-specific delivery areas, prices and existing order history are unchanged. Shipping is only one of several Checkout readiness checks.</span>
        </div>

        <div class="card p49-panel mb-4">
            <div class="card-header p49-panel-header">
                <h3 class="card-title"><i class="fas fa-plus-circle mr-2" aria-hidden="true"></i>Add Delivery Area</h3>
                <span class="p49-panel-hint">Homepage Checkout</span>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.homepage-shipping-charges.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="form_scope" value="create">
                    <div class="row align-items-end">
                        <div class="form-group col-lg-5">
                            <label for="p49-create-area">Delivery Area <span class="text-danger">*</span></label>
                            <input id="p49-create-area" type="text" name="area_name" class="form-control"
                                value="{{ old('form_scope') === 'create' ? old('area_name') : '' }}"
                                maxlength="255" placeholder="e.g. Inside Dhaka / Outside Dhaka" required>
                        </div>
                        <div class="form-group col-lg-3">
                            <label for="p49-create-charge">Delivery Charge (BDT) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                                <input id="p49-create-charge" type="number" name="delivery_charge" class="form-control"
                                    min="0" max="999999" step="1" inputmode="numeric"
                                    value="{{ old('form_scope') === 'create' ? old('delivery_charge') : '' }}" placeholder="e.g. 70" required>
                            </div>
                        </div>
                        <div class="form-group col-lg-2">
                            <label for="p49-create-status">Availability</label>
                            <select name="status" id="p49-create-status" class="form-control">
                                <option value="1" @selected(old('form_scope') !== 'create' || old('status') === '1')>Active</option>
                                <option value="0" @selected(old('form_scope') === 'create' && old('status') === '0')>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group col-lg-2">
                            <button type="submit" class="btn p49-btn-primary btn-block">
                                <i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Area
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Use real, approved delivery rates. Enter 0 only when delivery to that area is genuinely free.</small>
                </form>
            </div>
        </div>

        <div class="card p49-panel">
            <div class="card-header p49-panel-header">
                <h3 class="card-title"><i class="fas fa-map-marked-alt mr-2" aria-hidden="true"></i>Homepage Delivery Areas</h3>
                <span class="p49-panel-hint">{{ $areas->count() }} total</span>
            </div>
            <div class="card-body">
                @forelse($areas as $area)
                    @php
                        $editing = old('form_scope') === 'update' && (int) old('editing_shipping_id') === (int) $area->id;
                        $rowStatus = $editing ? old('status') : ($area->status ? '1' : '0');
                    @endphp
                    <div class="p49-area-row" id="area-{{ $area->id }}">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <div class="p49-area-name"><i class="fas fa-map-marker-alt mr-2" aria-hidden="true"></i> {{ $area->area_name }}</div>
                            <span class="badge badge-{{ $area->status ? 'success' : 'secondary' }} p49-status">
                                {{ $area->status ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('admin.homepage-shipping-charges.update', $area) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="form_scope" value="update">
                            <input type="hidden" name="editing_shipping_id" value="{{ $area->id }}">
                            <div class="row align-items-end">
                                <div class="form-group col-lg-5">
                                    <label for="p49-area-{{ $area->id }}">Delivery Area</label>
                                    <input id="p49-area-{{ $area->id }}" type="text" name="area_name"
                                        value="{{ $editing ? old('area_name') : $area->area_name }}" maxlength="255"
                                        class="form-control" required>
                                </div>
                                <div class="form-group col-lg-3">
                                    <label for="p49-charge-{{ $area->id }}">Delivery Charge (BDT)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                                        <input id="p49-charge-{{ $area->id }}" type="number" name="delivery_charge"
                                            value="{{ $editing ? old('delivery_charge') : $area->delivery_charge }}"
                                            min="0" max="999999" step="1" inputmode="numeric" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group col-lg-2">
                                    <label for="p49-status-{{ $area->id }}">Availability</label>
                                    <select id="p49-status-{{ $area->id }}" name="status" class="form-control">
                                        <option value="1" @selected((string) $rowStatus === '1')>Active</option>
                                        <option value="0" @selected((string) $rowStatus === '0')>Inactive</option>
                                    </select>
                                </div>
                                <div class="form-group col-lg-2">
                                    <button type="submit" class="btn btn-outline-success btn-block">
                                        <i class="fas fa-save mr-1" aria-hidden="true"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="p49-row-footer d-flex justify-content-between align-items-center flex-wrap">
                            <span><i class="fas fa-lock mr-1" aria-hidden="true"></i> Homepage only · ID {{ $area->id }}</span>
                            <form action="{{ route('admin.homepage-shipping-charges.destroy', $area) }}" method="POST"
                                onsubmit="return confirm('Remove this Homepage delivery area? Existing orders and Campaign delivery areas remain unchanged.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash-alt mr-1" aria-hidden="true"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p49-empty">
                        <div class="p49-empty-icon"><i class="fas fa-truck-loading" aria-hidden="true"></i></div>
                        <h4>No Homepage Delivery Areas Yet</h4>
                        <p class="text-muted mb-0">Create your first active area above. The Storefront Checkout will use it automatically.</p>
                    </div>
                @endforelse
            </div>
        </div>
        <p class="p49-disclaimer text-muted small mb-0"><i class="fas fa-shield-alt mr-1" aria-hidden="true"></i>
            To keep Checkout available, the last active Homepage area cannot be removed or deactivated until another area is active.
        </p>
    </div>
@stop

@section('css')
<style>
.p49-home-shipping{max-width:1280px;margin:0 auto;}
.p49-titlebar h1{font-weight:650;font-size:1.75rem;color:#173d30;}
.p49-hero{display:flex;align-items:center;gap:22px;border-radius:16px;padding:26px 30px;background:linear-gradient(115deg,#0c4a36 0%,#146d4b 70%,#258b62 100%);color:#fff;box-shadow:0 14px 28px rgba(15,79,53,.13)}
.p49-hero-symbol{display:flex;justify-content:center;align-items:center;flex:0 0 65px;width:65px;height:65px;border-radius:17px;font-size:30px;background:rgba(255,255,255,.13);}
.p49-hero h2{font-size:1.45rem;font-weight:700;margin:4px 0 6px}
.p49-hero p{color:#e0f7ec;font-size:.91rem;line-height:1.55}
.p49-eyebrow{letter-spacing:.13em;color:#bff6d6;font-size:.69rem;font-weight:800}
.p49-metric{background:#fff;border:1px solid #dbe8df;border-radius:12px;min-height:91px;padding:16px 18px;display:flex;align-items:center;gap:15px;box-shadow:0 5px 16px rgba(25,79,47,.035)}
.p49-metric-icon{width:42px;height:42px;border-radius:11px;display:grid;place-items:center;background:#edf8f1;font-size:19px;flex-shrink:0}
.p49-metric strong{display:block;font-size:1.6rem;line-height:1.2;color:#163e30;font-weight:750}
.p49-metric strong.p49-metric-status{font-size:1.1rem}
.p49-metric span:not(.p49-metric-icon){display:block;color:#64776a;font-size:.77rem;margin-top:3px}
.p49-note{display:flex;background:#f1faf5;border:1px solid #d2eadb;border-left:4px solid #2da56b;color:#20563e;border-radius:9px;padding:13px 15px;font-size:.89rem;line-height:1.5}
.p49-note i{margin-top:3px}
.p49-panel{border:1px solid #deebe4;border-radius:13px;overflow:hidden;box-shadow:0 9px 22px rgba(11,62,42,.045)}
.p49-panel .card-body{padding:20px}
.p49-panel-header{display:flex;justify-content:space-between;align-items:center;background:#f5faf7;border-bottom:1px solid #dfece4;padding:15px 20px}
.p49-panel-header h3{font-weight:700;color:#174d36;font-size:1.03rem;margin:0}
.p49-panel-hint{background:#e4f6ec;color:#20724b;border-radius:30px;padding:5px 12px;font-size:.74rem;font-weight:700}
.p49-home-shipping label{font-weight:650;color:#2c493b;font-size:.84rem;margin-bottom:7px}
.p49-home-shipping .form-control{border-radius:8px;border-color:#cadbd1;min-height:40px}
.p49-home-shipping .form-control:focus{box-shadow:0 0 0 .17rem rgba(32,142,88,.13);border-color:#23a568}
.p49-home-shipping .input-group-text{background:#f3f8f5;border-color:#cadbd1;color:#24754d;border-radius:8px 0 0 8px;font-weight:700}
.p49-btn-primary{background:#16834c;border-color:#16834c;color:#fff;font-weight:700;border-radius:8px;min-height:40px}
.p49-btn-primary:hover,.p49-btn-primary:focus{background:#0c6336;color:#fff}
.p49-area-row{background:#fff;border:1px solid #dce8e0;border-radius:12px;padding:17px 18px;margin-bottom:15px;transition:box-shadow .2s ease}
.p49-area-row:hover{box-shadow:0 8px 19px rgba(29,98,66,.07)}
.p49-area-name{font-weight:750;color:#183f30;font-size:1.02rem}
.p49-status{font-size:.7rem;padding:6px 13px;border-radius:25px}
.p49-row-footer{border-top:1px solid #edf2ef;padding-top:10px;color:#788981;font-size:.76rem;gap:8px}
.p49-row-footer form{margin:0}
.p49-empty{text-align:center;padding:35px 12px 42px}
.p49-empty-icon{width:72px;height:72px;border-radius:20px;margin:0 auto 15px;display:grid;place-items:center;background:#e9f7ee;color:#187549;font-size:28px}
.p49-empty h4{font-size:1.1rem;color:#1a543a;font-weight:700}
.p49-disclaimer{padding:6px 2px 20px}
@media(max-width:575.98px){.p49-hero{padding:21px 18px;gap:12px;align-items:flex-start}.p49-hero-symbol{width:44px;height:44px;flex-basis:44px;font-size:20px;border-radius:10px}.p49-hero h2{font-size:1.14rem}.p49-panel .card-body{padding:14px}.p49-area-row{padding:13px}.p49-panel-header{padding:14px}.p49-titlebar h1{font-size:1.43rem}}
</style>
@stop
