<?php

namespace Webkul\Shop\Http\Controllers;

use Webkul\Product\Repositories\SearchRepository;
use Webkul\Product\Repositories\ProductRepository;


 class SearchController extends Controller
{
    /**
     * SearchRepository object
     *
     * @var \Webkul\Product\Repositories\SearchRepository
    */
    protected $searchRepository;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Product\Repositories\SearchRepository  $searchRepository
     * @return void
    */
    public function __construct(SearchRepository $searchRepository,ProductRepository $productRepository)
    {
        $this->searchRepository = $searchRepository;
        $this->ProductRepository = $productRepository;

        parent::__construct();
    }

    /**
     * Index to handle the view loaded with the search results
     * 
     * @return \Illuminate\View\View 
     */
    public function index()
    {
        $results = $this->searchRepository->search(request()->all());
       
        return view($this->_config['view'])->with('results', $results->count() ? $results : null);
    }
     /*Certifsa */ 
    public function byCategory($id)
    {

        $results = $this->ProductRepository->getProductsRelatedToCategory($id);
        //return $results;
        return view($this->_config['view'])->with('results', $results->count() ? $results : null);
    }

    public function byCategoryCode($code)
    {

        $results = $this->ProductRepository->getProductsRelatedToCategoryCode($code);
      //  return $results;
        return view('shop::search.search')->with('results', $results->count() ? $results : null);
    }


     

   

}
