<form id="blockedCustomerAjaxForm" action="{{ $action }}" method="POST">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title font-weight-bold">
            <i class="fas {{ $isEdit ? 'fa-edit text-primary' : 'fa-user-slash text-danger' }} mr-2"></i>
            {{ $isEdit ? 'Edit Customer Block Rule' : 'Create Customer Block Rule' }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <div class="modal-body p-4">
        <div class="alert alert-danger d-none ajax-validation-errors" style="white-space: pre-line;"></div>

        <input type="hidden" name="source_order_id" value="{{ $blockedCustomer->source_order_id ?? '' }}">

        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="font-weight-bold">Customer Name</label>
                <input type="text"
                       name="customer_name"
                       value="{{ $blockedCustomer->customer_name ?? '' }}"
                       maxlength="255"
                       class="form-control"
                       placeholder="Customer name">
            </div>

            <div class="col-md-6 mb-3">
                <label class="font-weight-bold">Phone Number</label>
                <input type="text"
                       name="phone"
                       value="{{ $blockedCustomer->phone ?? '' }}"
                       maxlength="11"
                       inputmode="numeric"
                       class="form-control"
                       placeholder="01XXXXXXXXX">
                <small class="text-muted">Required only when Phone Block is selected.</small>
            </div>

            <div class="col-md-6 mb-3">
                <label class="font-weight-bold">IP Address</label>
                <input type="text"
                       name="ip_address"
                       value="{{ $blockedCustomer->ip_address ?? '' }}"
                       maxlength="45"
                       class="form-control"
                       placeholder="IPv4 or IPv6 address">
                <small class="text-muted">Required only when IP Block is selected.</small>
            </div>

            <div class="col-md-12 mb-3">
                <label class="font-weight-bold d-block">Identifiers to Block</label>
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="modalBlockPhone"
                           name="block_phone"
                           value="1"
                           @checked(($blockedCustomer->block_phone ?? true))>
                    <label class="custom-control-label" for="modalBlockPhone">Block Phone</label>
                </div>
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="modalBlockIp"
                           name="block_ip"
                           value="1"
                           @checked(($blockedCustomer->block_ip ?? false))>
                    <label class="custom-control-label" for="modalBlockIp">Block IP Address</label>
                </div>
            </div>

            <div class="col-md-12">
                <label class="font-weight-bold">Reason</label>
                <textarea name="reason"
                          rows="4"
                          maxlength="2000"
                          class="form-control"
                          placeholder="Why is this customer being blocked?">{{ $blockedCustomer->reason ?? '' }}</textarea>
            </div>
        </div>
    </div>

    <div class="modal-footer bg-light border-top-0">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save mr-1"></i>{{ $isEdit ? 'Update Rule' : 'Create Block Rule' }}
        </button>
    </div>
</form>
