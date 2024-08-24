@extends('vendors.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Create a support ticket') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('vendor.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Support Ticket') }}</a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Create a support ticket') }}</a>
      </li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <form action="{{ route('vendor.support_ticket.store') }}" enctype="multipart/form-data" method="POST">
          <div class="card-header">
            <div class="card-title d-inline-block">{{ __('Abrir um chamado') }}</div>
          </div>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-8 offset-lg-2">
                <div class="alert alert-danger pb-1 dis-none" id="equipmentErrors">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <ul></ul>
                </div>

                @csrf
                <div class="row">
                  <div class="col-lg-6">
                    <div class="form-group">
                      <label>{{ __('E-mail') . '*' }}</label>
                      <input type="email" class="form-control" value="{{ Auth::guard('vendor')->user()->email }}"
                        name="email" placeholder="E-mail da sua conta">
                    </div>
                    @error('email')
                      <p class="text-danger">{{ $message }}</p>
                    @enderror
                  </div>

                  <div class="col-lg-6">
                    <div class="form-group">
                      <label>{{ __('Assunto') . '*' }}</label>
                      <input type="text" class="form-control" name="subject" placeholder="Do que se trata?">
                    </div>
                    @error('subject')
                      <p class="text-danger">{{ $message }}</p>
                    @enderror
                  </div>

                  <div class="col-lg-12">
                    <div class="form-group">
                      <label>{{ __('Descrição') }}</label>
                      <textarea name="description" rows="4" class="form-control summernote"></textarea>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="form-group">
                      <label for="">{{ __('Anexo') }}</label>
                      <div class="input-group">
                        <div class="custom-file">
                          <input type="file" name="attachment" accept=".zip" class="custom-file-input"
                            id="zip_filess">
                          <label class="custom-file-label" for="zip_filess">{{ __('Buscar arquivo...') }}</label>
                        </div>
                      </div>
                      <p class="text-warning">{{ __('Carregue apenas arquivos ZIP, o tamanho máximo do arquivo é 20MB') }}</p>
                      @error('attachment')
                        <p class="text-danger">{{ $message }}</p>
                      @enderror
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <div class="card-footer">
            <div class="row">
              <div class="col-12 text-center">
                <button type="submit" class="btn btn-success">
                  {{ __('Enviar') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
