@extends('gp247-core::layout')
@section('main')
      <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
          <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
            
            <li class="nav-item">
              <a class="nav-link active" id="tab-admin-config-tab" data-toggle="pill" href="#tab-admin-config" role="tab" aria-controls="tab-admin-config" aria-selected="false">{{ gp247_language_render('Plugins/PaypalExpress::lang.config_paypal') }}</a>
            </li>
          </ul>
        </div>
        
        <div class="card-body">
          <div class="tab-content" id="custom-tabs-four-tabContent">

              {{-- Tab admin product --}}
              <div class="tab-pane fade active show" id="tab-admin-config" role="tabpanel" aria-labelledby="tab-admin-config-tab">
                  <div class="row">

                        <div class="col-md-5">
                          <div class="card">
                            <div class="card-header with-border">
                              <h3 class="card-title">{{ gp247_language_render('Plugins/PaypalExpress::lang.config_paypal') }}</h3>
                            </div>
                      
                            <div class="card-body table-responsivep-0">
                             <table class="table table-hover box-body text-wrap table-bordered">
                               <tbody>
                                    <tr>
                                          <td>{{ gp247_language_render('Plugins/PaypalExpress::lang.order_status_success') }}</td>
                                          <td><a href="#" class="editable-required" data-name="PaypalExpress_order_status_success" data-type="select" data-pk="" data-source="{{ json_encode($orderStatusSuccess) }}" data-url="{{ $urlUpdateConfigGlobal }}" data-title="{{ gp247_language_render('Plugins/PaypalExpress::lang.order_status_success') }}" data-value="{{ gp247_config('PaypalExpress_order_status_success') }}"  data-original-title="" title="" data-placement="left"></a></td>
                                    </tr>
                                    <tr>
                                          <td>{{ gp247_language_render('Plugins/PaypalExpress::lang.payment_status_success') }}</td>
                                          <td><a href="#" class="editable-required" data-name="PaypalExpress_payment_status_success" data-type="select" data-pk="" data-source="{{ json_encode($paymentStatusSuccess) }}" data-url="{{ $urlUpdateConfigGlobal }}" data-title="{{ gp247_language_render('Plugins/PaypalExpress::lang.payment_status_success') }}" data-value="{{ gp247_config('PaypalExpress_payment_status_success') }}"  data-original-title="" title="" data-placement="left"></a></td>
                                    </tr>
                                    <tr>
                                      <td>{{ gp247_language_render('Plugins/PaypalExpress::lang.order_status_refunded') }}</td>
                                      <td><a href="#" class="editable-required" data-name="PaypalExpress_order_status_refunded" data-type="select" data-pk="" data-source="{{ json_encode($orderStatusSuccess) }}" data-url="{{ $urlUpdateConfigGlobal }}" data-title="{{ gp247_language_render('Plugins/PaypalExpress::lang.order_status_refunded') }}" data-value="{{ gp247_config('PaypalExpress_order_status_refunded') }}"  data-original-title="" title="" data-placement="left"></a></td>
                                  </tr>
                                    <tr>
                                          <td>{{ gp247_language_render('Plugins/PaypalExpress::lang.payment_status_refunded') }}</td>
                                          <td><a href="#" class="editable-required" data-name="PaypalExpress_payment_status_refunded" data-type="select" data-pk="" data-source="{{ json_encode($paymentStatusSuccess) }}" data-url="{{ $urlUpdateConfigGlobal }}" data-title="{{ gp247_language_render('Plugins/PaypalExpress::lang.payment_status_refunded') }}" data-value="{{ gp247_config('PaypalExpress_payment_status_refunded') }}"  data-original-title="" title="" data-placement="left"></a></td>
                                    </tr>
                               </tbody>
                             </table>
                            </div>
                          </div>
                        </div>
                      </div>
              </div>
              {{-- // admin product --}}
          </div>
        </div>
        <!-- /.card -->
</div>

@endsection

@push('styles')
<!-- Ediable -->
<link rel="stylesheet" href="{{ gp247_file('GP247/Core/plugin/bootstrap-editable.css')}}">
<style type="text/css">
  #maintain_content img{
    max-width: 100%;
  }
</style>
@endpush

@if (empty($dataNotFound))
@push('scripts')
<!-- Ediable -->
<script src="{{ gp247_file('GP247/Core/plugin/bootstrap-editable.min.js')}}"></script>

<script type="text/javascript">

  // Editable
$(document).ready(function() {

      //  $.fn.editable.defaults.mode = 'inline';
      $.fn.editable.defaults.params = function (params) {
        params._token = "{{ csrf_token() }}";
        params.storeId = "{{ $storeId }}";
        return params;
      };

      $('.editable-required').editable({
        validate: function(value) {
            if (value == '') {
                return '{{  gp247_language_render('admin.not_empty') }}';
            }
        },
        success: function(data) {
          if(data.error == 0){
            alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
          } else {
            alertJs('error', data.msg);
          }
      }
    });

    $('.editable').editable({
        validate: function(value) {
        },
        success: function(data) {
          console.log(data);
          if(data.error == 0){
            alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
          } else {
            alertMsg('error', data.msg);
          }
      }
    });

});


$('input.check-data-config').iCheck({
    checkboxClass: 'icheckbox_square-blue',
    radioClass: 'iradio_square-blue',
    increaseArea: '20%' /* optional */
  }).on('ifChanged', function(e) {
  var isChecked = e.currentTarget.checked;
  isChecked = (isChecked == false)?0:1;
  var name = $(this).attr('name');
    $.ajax({
      url: '{{ $urlUpdateConfig }}',
      type: 'POST',
      dataType: 'JSON',
      data: {
          "_token": "{{ csrf_token() }}",
          "name": $(this).attr('name'),
          "storeId": $(this).data('store'),
          "value": isChecked
        },
    })
    .done(function(data) {
      if(data.error == 0){
        alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
      } else {
        alertJs('error', data.msg);
      }
    });

    });

  $('input.check-data-config-global').iCheck({
    checkboxClass: 'icheckbox_square-blue',
    radioClass: 'iradio_square-blue',
    increaseArea: '20%' /* optional */
  }).on('ifChanged', function(e) {
  var isChecked = e.currentTarget.checked;
  isChecked = (isChecked == false)?0:1;
  var name = $(this).attr('name');
    $.ajax({
      url: '{{ $urlUpdateConfigGlobal }}',
      type: 'POST',
      dataType: 'JSON',
      data: {
          "_token": "{{ csrf_token() }}",
          "name": $(this).attr('name'),
          "value": isChecked
        },
    })
    .done(function(data) {
      if(data.error == 0){
        if (isChecked == 0) {
          $('#smtp-config').hide();
        } else {
          $('#smtp-config').show();
        }
        alertJs('success', '{{ gp247_language_render('admin.msg_change_success') }}');
      } else {
        alertJs('error', data.msg);
      }
    });

    });


</script>




<script>
  // Update store_info

//End update store_info
</script>

@endpush
@endif