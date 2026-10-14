"""Exercise the real socket/PUT parser over verified TLS; never print request/response data."""
import binascii
import json
import os
import ssl
import struct
import sys
import time
import urllib.error
import urllib.request
import zlib

stage = "startup"
context = ssl.create_default_context(cafile=sys.argv[2])
base = "https://localhost:" + sys.argv[1] + "/api/v1"


def request(method, path, expected=200, body=None, token=None, content_type="application/json"):
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    if body is not None:
        headers["Content-Type"] = content_type
        if not isinstance(body, bytes):
            body = json.dumps(body).encode()
    req = urllib.request.Request(base + path, data=body, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req, context=context, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        assert response.status == expected
        data = response.read()
        return json.loads(data) if data and "json" in response.headers.get("Content-Type", "") else data


try:
    for attempt in range(30):
        try:
            assert request("GET", "/health") == {"status": "ok"}
            break
        except (OSError, AssertionError):
            if attempt == 29:
                raise
            time.sleep(1)
    stage = "login"
    token = request("POST", "/auth/login", body={"username": os.environ["ADMIN_USERNAME"],
        "password": os.environ["ADMIN_PASSWORD"]})["access_token"]
    stage = "catalog"
    category = request("GET", "/categories", token=token)["items"][0]["id"]
    book = request("POST", "/books", 201, {"title": "Socket fixture", "author": "Fixture",
        "isbn": "0000000000101", "category_id": category, "total_copies": 1}, token)
    stage = "multipart-put"
    def chunk(kind, data):
        return struct.pack("!I", len(data)) + kind + data + struct.pack("!I", binascii.crc32(kind + data))
    png = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack("!IIBBBBB", 1, 1, 8, 2, 0, 0, 0))
    png += chunk(b"IDAT", zlib.compress(b"\0\xff\0\0"))
    padding_size = 2_000_000 - len(png) - 24
    png += chunk(b"tEXt", b"padding\0" + b"x" * (padding_size - 8)) + chunk(b"IEND", b"")
    assert len(png) == 2_000_000
    boundary = "fixture-" + binascii.hexlify(os.urandom(16)).decode()
    multipart = ("--" + boundary + '\r\nContent-Disposition: form-data; name="file"; filename="image.txt"'
        + "\r\nContent-Type: text/plain\r\n\r\n").encode() + png + ("\r\n--" + boundary + "--\r\n").encode()
    request("PUT", "/books/" + book["id"] + "/cover", body=multipart, token=token,
        content_type="multipart/form-data; boundary=" + boundary)
    assert request("GET", "/books/" + book["id"] + "/cover", token=token) == png
    stage = "multipart-size-and-content-rejection"
    oversized = multipart.replace(png, png + b"x" * 1_000_000)
    invalid = multipart.replace(png, b"not an image")
    for rejected in (oversized, invalid):
        result = request("PUT", "/books/" + book["id"] + "/cover", 422, rejected, token,
            "multipart/form-data; boundary=" + boundary)
        assert result["code"] == "VALIDATION_ERROR"
    assert request("GET", "/books/" + book["id"] + "/cover", token=token) == png
    stage = "loan-return-report"
    member = request("POST", "/members", 201, {"full_name": "Socket member", "document_number": "SOCKET-1"}, token)
    loan = request("POST", "/loans", 201, {"member_id": member["id"], "items": [{"book_id": book["id"]}]}, token)
    request("POST", "/loans/" + loan["id"] + "/items/" + loan["items"][0]["id"] + "/return", token=token)
    report = request("GET", "/reports/most-borrowed?from=2026-10-14&to=2026-10-14", token=token)
    assert report["rows"][0]["times_borrowed"] == 1
    stage = "cover-delete"
    request("DELETE", "/books/" + book["id"] + "/cover", 204, token=token)
    request("GET", "/books/" + book["id"] + "/cover", 404, token=token)
except Exception:
    print("::error::Verified TLS smoke failed at stage: " + stage)
    raise SystemExit(1)
print("Verified TLS socket, admin bootstrap, multipart PUT, loan, return and report passed.")
