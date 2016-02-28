<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>書誌情報確認</title>
	</head>
	<body>
		<form action="awsISBN.php" method="post">
			ISBN : <input type="text" name="isbn" size="30" value="" />
			<input type="submit" value="検索" />
		</form>
		<?php

// --------------------------------------------------------------------------------------
// ---------------------------------------初期設定---------------------------------------
// --------------------------------------------------------------------------------------

			// アクセスキーとシークレットキーは bil0122.do@gmail.com で「Product Advertising API」に登録したものを使用中．
			// https://affiliate.amazon.co.jp/gp/advertising/api/detail/your-account.html
			define("ACCESS_KEY_ID"     , '***');
			define("ASSOCIATE_TAG"     , '***');

			//アソシエイトタグはアフィリエイトで収益を得る際に必要．パラメータとしては必須だが，値はデタラメでも可．
			define("SECRET_ACCESS_KEY" , '***');

			// 仕様変更
			// 変更前：[http://webservices.amazon.co.jp/onca/xml]
			define("ACCESS_URL"        , 'http://ecs.amazonaws.jp/onca/xml');

			// 各パラメータの設定
			$params = array();
			$params['Service']        = 'AWSECommerceService';
			$params['Version']        = '2011-08-01';
			$params['Operation']      = 'ItemLookup';
			$params['ItemId']         = $_POST["isbn"];
			$params['IdType']         = 'ISBN';
			$params['SearchIndex']    = "Books";
			$params['AssociateTag']   = ASSOCIATE_TAG;
			$params['ResponseGroup']  = 'ItemAttributes,Offers,Images,Reviews';
			$params['Timestamp']      = gmdate('Y-m-d\TH:i:s\Z');

// --------------------------------------------------------------------------------------
// ---------------------------------リクエストURL作成用関数-----------------------------
// --------------------------------------------------------------------------------------

			// 日本語や一部の記号を 16 進数に変換する関数
			function urlencode_RFC3986($str)
			{
				return str_replace('%7E', '~', rawurlencode($str));
			}

			// 各パラメータを用いて Amazon にアクセスするためのリクエストURLを生成する関数
			function createURLfromISBN($params)
			{
				//パラメータを自然順序付け・昇順で並び替え
				ksort($params);

				$base_param = 'AWSAccessKeyId='.ACCESS_KEY_ID;

				// 各パラメータを「&」で結合
				foreach ($params as $key => $value)
				{
					$base_param .= '&'.urlencode_RFC3986($key).'='.urlencode_RFC3986($value);
				}

				// ベースURLとパラメータの結合して，リクエストURLを生成
				$parsed_url = parse_url(ACCESS_URL);

				// 署名を計算するための文字列を生成
				$string_to_sign = "GET\n{$parsed_url['host']}\n{$parsed_url['path']}\n{$base_param}";

				// 「シークレットキーを用いてリクエストURLをハッシュ値に変換したもの=署名」を生成
				$signature = base64_encode(hash_hmac('sha256', $string_to_sign, SECRET_ACCESS_KEY, true));

				// リクエストURLの生成
				// ベースURL + パラメータ + 署名
				$url = ACCESS_URL.'?'.$base_param.'&Signature='.urlencode_RFC3986($signature);

				return $url;
			}

			// クエストURLにより取得したXMLファイルから必要な情報を抽出する関数
			function getInfoFromXML($url)
			{
				//Amazonへレスポンス
				$response = file_get_contents($url);

				$parsed_xml = null;
				$data = array();

				// レスポンスを配列で取得
				if (isset($response))
				{
					$parsed_xml = simplexml_load_string($response);
				}

				// Amazonへのレスポンスが正常に行われていたら
				if ($response && isset($parsed_xml) && !$parsed_xml->faultstring && !$parsed_xml->Items->Request->Errors)
				{
					foreach ($parsed_xml->Items->Item as $current)
					{
						// 情報の取得
						$title        = $current->ItemAttributes->Title;
						$author       = $current->ItemAttributes->Author;
						$publicationdate = $current->ItemAttributes->PublicationDate;
						$manufacturer = $current->ItemAttributes->Manufacturer;
						$imgURL       = $current->MediumImage->URL;
						$bookURL      = $current->DetailPageURL;

						// 全角を半角に変換，string型
						$title = mb_convert_kana($title, "as", "UTF-8");

					}

					// 出版年を年と月に分割，配列，各要素はstring型
					$str = (string)$publicationdate;
					$dates = explode('-', $str);

					// 著者が複数いる場合
					$authors = $author[0];
					if (count($author) > 1)
					{
							for ($i = 1; $i < count($author); $i++)
							{
								$authors = $authors. ", ". $author[$i];
							}
					}

					// 
					array_push($data,$title,(string)$authors,$dates,(string)$manufacturer,(string)$imgURL,(string)$bookURL);

					return $data;
				}
			}

// --------------------------------------------------------------------------------------
// ---------------------------------リクエストURL作成用関数-----------------------------
// --------------------------------------------------------------------------------------

			$url = createURLfromISBN($params);
			$result = getInfoFromXML($url);
			print_r($result);
		?>
		</form>
	</body>
</html>
