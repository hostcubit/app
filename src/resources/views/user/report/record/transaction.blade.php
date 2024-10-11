@extends('user.layouts.app')
@section('panel')

    <main class="main-body">
        <div class="container-fluid px-0 main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h2>{{ $title }}</h2>
                    <div class="breadcrumb-wrapper">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="{{ route("user.dashboard") }}">{{ translate("Dashboard") }}</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
            <div class="table-filter mb-4">
                <form action="{{route(Route::currentRouteName())}}" class="filter-form">
                   
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="filter-search">
                                <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Filter by trx code or amount") }}" />
                                <span><i class="ri-search-line"></i></span>
                            </div>
                        </div>
                        
                        <div class="col-xxl-5 col-lg-7 offset-xxl-3">
                            <div class="filter-action">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                                    <span class="input-group-text" id="filterByDate">
                                        <i class="ri-calendar-2-line"></i>
                                    </span>
                                </div>

                                <div class="d-flex align-items-center gap-3">
                                    <button type="submit" class="filter-action-btn ">
                                        <i class="ri-menu-search-line"></i> {{ translate("Filters") }}
                                    </button>
                                    <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName())}}">
                                        <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <h4 class="card-title">{{ translate("Transaction History") }}</h4>
                    </div>
                </div>
                <div class="card-body px-0 pt-0">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">{{ translate("Date") }}</th>
                                    <th scope="col">{{ translate("Trx Number") }}</th>
                                    <th scope="col">{{ translate("Amount") }}</th>
                                    <th scope="col">{{ translate("Details") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $transaction)
                                    <tr class="@if($loop->even)@endif">
                                        <td data-label="{{ translate('Date')}}">
                                            {{diffForHumans($transaction->created_at)}}
                                            <p class="text-dark fw-semibold">{{getDateTime($transaction->created_at)}}</p>
                                        </td>

                                        <td data-label="{{ translate('Trx Number')}}">
                                            <p class="text-dark fw-semibold">{{$transaction->transaction_number}}</p>
                                        </td>
                                        <td data-label="{{ translate('Amount')}}">
                                            <span class=" i-badge dot @if($transaction->transaction_type == "+") dark-soft @else danger-soft @endif pill">
                                                {{$transaction->transaction_type == "+" ? '+' : '-'}}{{shortAmount($transaction->amount)}}  {{$transaction->paymentGateway ? $transaction->paymentGateway->currency_code : translate("N\A")}}
                                            </span></span>

                                        </td>

                                         <td data-label="{{ translate('Details')}}">
                                            {{$transaction->details}}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('user.partials.pagination', ['paginator' => $transactions])
                </div>
            </div>
        </div>
    </main>
@endsection
@push('script-push')
<script>
	"use strict";
		flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            mode: "range",
        });
</script>
@endpush
