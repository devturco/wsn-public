<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Sitemap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Session;
use Spatie\Sitemap\SitemapGenerator;

class SitemapController extends Controller
{
    protected string $path;
    public function __construct()
    {
        $this->path = public_path('assets/front/files');
    }
    public function index()
    {
        $data['sitemaps'] = Sitemap::query()
            ->orderBy('id', 'DESC')
            ->paginate(10);
        return view('backend.sitemap.index', $data);
    }
    public function store(Request $request)
    {
        $messages = [
            'sitemap_url.required' => 'The sitemap url field is required'
        ];
        $rules = [
            'sitemap_url' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            $validator->getMessageBag()->add('error', 'true');
            return response()->json($validator->errors());
        }
        $data = new Sitemap();
        $input = $request->all();
        @mkdir($this->path, 0755, true);
        $filename = 'sitemap' . uniqid() . '.xml';
        SitemapGenerator::create($request->sitemap_url)->writeToFile($this->path . '/' . $filename);
        $input['filename']    = $filename;
        $input['sitemap_url'] = $request->sitemap_url;
        $data->fill($input)->save();
        Session::flash('success', 'Sitemap Generate Successfully');

        return response()->json(['status' => 'success'], 200);
    }
    public function download(Request $request)
    {
        return response()->download($this->path . '/' . $request->filename);
    }

    public function delete($id): RedirectResponse
    {
        $sitemap = Sitemap::query()->find($id);
        @unlink($this->path . '/' . $sitemap->filename);
        $sitemap->delete();
        Session::flash('success', 'Sitemap file deleted successfully!');
        return back();
    }
}
