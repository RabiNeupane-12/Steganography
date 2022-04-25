<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecodeRequest;
use App\Http\Requests\EncodeRequest;
use App\Http\Traits\EncodeDecodeTrait;
use App\Models\Gallery;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;


class UploadController extends Controller
{
    use EncodeDecodeTrait;
    public function encode(EncodeRequest $request){
        
        if(!str_contains($request->encode, 'data:image'))
            return ['message'=>'Invalid Base64 Image'];

        $base64File = $request->input('encode');

        // decode the base64 file
        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64File));
        
        // save it to temporary dir first.
        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
        file_put_contents($tmpFilePath, $fileData);
        
        // this just to help us get file info.
        $tmpFile = new File($tmpFilePath);
        
        $file = new UploadedFile(
            $tmpFile->getPathname(),
            $tmpFile->getFilename(),
            $tmpFile->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        );

        
        $encrypter = new \Illuminate\Encryption\Encrypter($request->passphrase, 'AES-128-CBC');

        $encrptedText = $encrypter->encrypt($request->encode_text);

        $imageInfo = $this->steganize($file,$encrptedText,true);
        
        $imageName = $imageInfo[0];
        $path = 'images/'.$imageName;
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        // Save to gallery
        $gallery = new Gallery();
        $gallery->user_id = auth()->user()->id;
        $gallery->image = $imageName;
        $gallery->public = 0;
        $gallery->passphrase = $request->passphrase;

        if($gallery->save())
            return response()->json(['message'=>'Image Encoded Successfully','image'=>$base64]);
    
    }

    public function decode(DecodeRequest $request)
    {
        $base64File = $request->input('decode');
        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64File));

        // save it to temporary dir first.
        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
        file_put_contents($tmpFilePath, $fileData);
        
        $tmpFile = new File($tmpFilePath);
        $encrypter = new \Illuminate\Encryption\Encrypter($request->passphrase, 'AES-128-CBC');

        try
        {
            return $encrypter->decrypt($this->desteganize($tmpFile));
        }
        catch(DecryptException $e)
        {
            return ['message' => 'Invalid Passphrase'];
        }catch(Exception $e)
        {
            return ['message' => 'Something went wrong'];
        }
    }

}
