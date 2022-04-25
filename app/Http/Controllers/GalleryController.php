<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecodeRequest;
use App\Http\Requests\EncodeRequest;
use App\Models\Gallery;
use Illuminate\Http\Request;
use App\Http\Traits\EncodeDecodeTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Encryption\Encrypter;

class GalleryController extends Controller
{
    use EncodeDecodeTrait;
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $gallery = Gallery::where('public',1)->get();
        // dd($gallery);
        return view('gallery', compact('gallery'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        $gallery = Gallery::where('id', $id)->firstorFail();
        if($gallery->delete()){
            return  redirect()->route('user.index')->with("message", "History Deleted Successfully");

        }
    }

    public function encode(EncodeRequest $request)
    {
        // dd(request()->all());

        $image = $request->encode;
        
        $encrypter = new \Illuminate\Encryption\Encrypter($request->passphrase, 'AES-128-CBC');

        $plainText = $encrypter->encrypt($request->encode_text);

        $imageInfo = $this->steganize($image,$plainText);
        // dd($imageInfo);
        if(isset($imageInfo[0]))
        {
            $gallery = new Gallery();
            $gallery->user_id = auth()->user()->id;
            // if($gallery->user_id == null)
            //     $gallery->user_id = 1;
            $gallery->image = $imageInfo[0]; 
            $gallery->public = $request->visibility == 'public'?1:0;
            // dd($gallery->public);
            if($gallery->public == 1)
            $gallery->text = $request->message;
            $gallery->before = json_encode($imageInfo[1]);
            $gallery->after = json_encode($imageInfo[2]);
            //Save MSE and PSNR value to column
            $gallery->mse = $imageInfo[3];
            $gallery->psnr = $imageInfo[4];
            $gallery->passphrase = $request->passphrase;

            if($gallery->save())
                return redirect('/gallery')->with('message','Image Encoded Successfully')
                                           ->with('encode',$imageInfo[0]);
        }
        else
        {
            return redirect('/gallery')->with('error','Length of Text is too big! Please try again');
        }
    }

    public function decode(DecodeRequest $request)
    {
        $file = $request->decode;

        $encrypter = new \Illuminate\Encryption\Encrypter($request->passphrases, 'AES-128-CBC');

        try{
            $decodedText = $encrypter->decrypt($this->desteganize($file));
        }
        catch(\Exception $e){
            return redirect('/gallery')->with('decode_error','The passphrase didn\'t match!');
        }
        
        return redirect('/gallery')->with('decodedText',$decodedText);
    }
}
