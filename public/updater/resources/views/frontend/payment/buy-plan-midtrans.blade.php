<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>{{ $websiteInfo->website_title }}</title>
  <style>
    #pay-button {
      display: none
    }
  </style>
</head>

<body>
  <button class="btn btn-primary" id="pay-button">{{ __('Pay Now') }}</button>
  <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
  @if ($data['midtrans_mode'] == 1)
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key') }}">
    </script>
  @else
    <script src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key') }}"></script>
  @endif
  <script>
    var baseUrl = "{{ route('index') }}";
    const payButton = document.querySelector('#pay-button');
    payButton.addEventListener('click', function(e) {
      e.preventDefault();

      snap.pay('{{ $snapToken }}', {
        // Optional
        onSuccess: function(result) {
          window.location.href = baseUrl + "/vendor/membership/midtrans/notify";
        },
        // Optionall
        onPending: function(result) {
          window.location.href = baseUrl + "/midtrans/cancel";
        },
        // Optional
        onError: function(result) {

          window.location.href = baseUrl + "/midtrans/cancel";

        }
      });
    });
    payButton.click()
  </script>
</body>

</html>
