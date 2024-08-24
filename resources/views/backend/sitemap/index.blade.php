@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Sitemap') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator">
        <i class="flaticon-right-arrow"></i>
      </li>
      <li class="nav-item">
        <a href="#">{{ __('Sitemap') }}</a>
      </li>

    </ul>
  </div>
  <div class="row">
    <div class="col-md-12">

      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-4">
              <div class="card-title d-inline-block">{{ __('Sitemap') }}</div>
            </div>
            <div class="col-lg-3">

            </div>
            <div class="col-lg-4 offset-lg-1 mt-2 mt-lg-0">
              <a href="#" class="btn btn-primary float-right btn-sm" data-toggle="modal"
                data-target="#createModal"><i class="fas fa-plus"></i> {{ __('Add Sitemap') }}</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-lg-12">
              @if (count($sitemaps) == 0)
                <h3 class="text-center">{{ __('NO SITEMAP FOUND') }}</h3>
              @else
                <div class="table-responsive">
                  <table class="table table-striped mt-3">
                    <thead>
                      <tr>
                        <th scope="col">{{ __('File Name') }}</th>
                        <th scope="col">{{ __('Sitemap Url') }}</th>
                        <th scope="col">{{ __('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($sitemaps as $key => $sitemap)
                        <tr>
                          <td>{{ $sitemap->filename }}</td>
                          <td>
                            <a href="{{ url('assets/front/files/' . $sitemap->filename) }}" target="_blank">
                              {{ asset('assets/front/files/' . $sitemap->filename) }}
                            </a>
                          </td>
                          <td>
                            <form class="d-inline-block" action="{{ route('admin.sitemap.download', $sitemap->id) }}"
                              method="post">
                              @csrf
                              <input type="hidden" name="filename" value="{{ $sitemap->filename }}">
                              <button type="submit" class="btn btn-secondary my-2 btn-sm">
                                <span class="btn-label">
                                  <i class="fas fa-arrow-alt-circle-down"></i>
                                </span>

                              </button>
                            </form>
                            <form class="deleteform d-inline-block"
                              action="{{ route('admin.sitemap.delete', $sitemap->id) }}" method="post">
                              @csrf
                              <button type="submit" class="btn btn-danger btn-sm deletebtn">
                                <span class="btn-label">
                                  <i class="fas fa-trash"></i>
                                </span>

                              </button>
                            </form>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>



  <div class="modal fade" id="createModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Add Sitemap') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="ajaxForm" class="modal-form create" action="{{ route('admin.sitemap.store') }}" method="POST">
            @csrf

            <input type="hidden" name="filename">

            <div class="form-group">
              <label for="">{{ __('Sitemap Url') }} **</label>
              <input type="text" class="form-control" name="sitemap_url" placeholder="{{ __('Enter Sitemap Url') }}">
              <p id="errsitemap_url" class="mb-0 text-danger em"></p>
            </div>

          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
          <button id="submitBtn" type="button" class="btn btn-primary">{{ __('Submit') }}</button>
        </div>
      </div>
    </div>
  </div>

@endsection
