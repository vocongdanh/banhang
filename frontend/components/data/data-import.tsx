"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { UploadIcon, FileIcon, AlertTriangleIcon, CheckCircle2Icon } from "lucide-react"
import { useToast } from "@/components/ui/use-toast"

export function DataImport() {
  const [fileObj, setFileObj] = useState<File | null>(null)
  const [importTarget, setImportTarget] = useState<string>("products")
  const [isUploading, setIsUploading] = useState(false)
  const [importResult, setImportResult] = useState<any>(null)
  const { toast } = useToast()
  
  // For tracking button state
  const [buttonEnabled, setButtonEnabled] = useState(false)

  const [isLoggedIn, setIsLoggedIn] = useState(false);

  // Use effect to monitor file state changes
  useEffect(() => {
    console.log("File state changed:", fileObj ? fileObj.name : "no file");
    setButtonEnabled(!!fileObj);
  }, [fileObj]);

  useEffect(() => {
    // Kiểm tra token với key đúng là auth_token
    const token = localStorage.getItem('auth_token');
    setIsLoggedIn(!!token);
  }, []);

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = event.target.files;
    console.log("File input changed, files:", files);
    
    if (files && files.length > 0) {
      const file = files[0];
      console.log("Selected file:", file.name, file.type);
      setFileObj(file);
    } else {
      console.log("No file selected");
      setFileObj(null);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!fileObj) {
      toast({
        title: "Lỗi",
        description: "Vui lòng chọn tệp tin để nhập dữ liệu",
        variant: "destructive"
      })
      return
    }

    setIsUploading(true)
    setImportResult(null)
    
    try {
      const formData = new FormData()
      formData.append("file", fileObj)
      formData.append("target", importTarget)
      
      // Lấy token từ localStorage với key đúng
      const token = localStorage.getItem('auth_token');
      
      // Gọi API với header Authorization
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/import`, {
        method: "POST",
        headers: token ? {
          'Authorization': `Bearer ${token}`
        } : {},
        body: formData,
        credentials: 'include',
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || "Lỗi khi nhập dữ liệu")
      }

      const result = await response.json()
      setImportResult(result)

      toast({
        title: "Thành công",
        description: `Đã nhập dữ liệu thành công`,
      })
    } catch (error) {
      console.error("Import error:", error)
      toast({
        title: "Lỗi",
        description: error instanceof Error ? error.message : "Lỗi không xác định khi nhập dữ liệu",
        variant: "destructive"
      })
    } finally {
      setIsUploading(false)
    }
  }

  // Hiển thị thông báo nếu chưa đăng nhập
  if (!isLoggedIn) {
    return (
      <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
        <h3 className="text-lg font-semibold mb-2">Bạn cần đăng nhập trước</h3>
        <p>Vui lòng <a href="/login" className="text-blue-600 hover:underline">đăng nhập</a> để sử dụng tính năng này.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="bg-yellow-50 border-yellow-200 border p-4 rounded-md mb-4">
        <p className="text-sm text-yellow-700">
          <strong>Chú ý:</strong> Tính năng nhập dữ liệu đã được cập nhật và hỗ trợ nhiều định dạng file hơn, bao gồm cả .docx, .pdf, .csv, v.v.
        </p>
      </div>
      
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="importTarget">Chọn loại dữ liệu</Label>
          <Select
            value={importTarget}
            onValueChange={setImportTarget}
          >
            <SelectTrigger id="importTarget">
              <SelectValue placeholder="Chọn loại dữ liệu" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="products">Sản phẩm</SelectItem>
              <SelectItem value="categories">Danh mục</SelectItem>
              <SelectItem value="customers">Khách hàng</SelectItem>
              <SelectItem value="suppliers">Nhà cung cấp</SelectItem>
            </SelectContent>
          </Select>
          <p className="text-sm text-muted-foreground">
            Chọn loại dữ liệu bạn muốn nhập vào hệ thống
          </p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="fileInput">Chọn tệp tin</Label>
          <Input
            id="fileInput"
            type="file"
            onChange={handleFileSelect}
            accept=".csv,.xlsx,.xls,.doc,.docx,.ppt,.pptx,.rtf,.txt,.pdf"
            disabled={isUploading}
          />
          <p className="text-sm text-muted-foreground">
            Hỗ trợ các định dạng: CSV, Excel (.xlsx, .xls), Word (.doc, .docx), PowerPoint (.ppt, .pptx), RTF, TXT, PDF
          </p>
        </div>

        {fileObj && (
          <div className="flex items-center gap-2 p-2 bg-muted rounded-md">
            <FileIcon className="h-5 w-5 text-blue-500" />
            <span className="text-sm font-medium">{fileObj.name}</span>
            <span className="text-xs text-muted-foreground ml-auto">
              {(fileObj.size / 1024).toFixed(1)} KB
            </span>
          </div>
        )}

        <Button 
          type="submit" 
          disabled={!buttonEnabled || isUploading}
          className="w-full"
        >
          {isUploading ? (
            <span className="flex items-center">
              <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Đang xử lý...
            </span>
          ) : (
            <span className="flex items-center">
              <UploadIcon className="mr-2 h-5 w-5" />
              Nhập dữ liệu
            </span>
          )}
        </Button>
      </form>

      {importResult && (
        <Alert className={importResult.success ? "border-green-500" : "border-red-500"}>
          {importResult.success ? (
            <CheckCircle2Icon className="h-4 w-4 text-green-500" />
          ) : (
            <AlertTriangleIcon className="h-4 w-4 text-red-500" />
          )}
          <AlertTitle>
            {importResult.success ? "Nhập dữ liệu thành công" : "Lỗi nhập dữ liệu"}
          </AlertTitle>
          <AlertDescription>
            {importResult.success ? (
              <div className="space-y-1">
                <p>Đã tải lên thành công file dữ liệu.</p>
                {importResult.data && importResult.data.file_name && (
                  <p>Tên file: {importResult.data.file_name}</p>
                )}
              </div>
            ) : (
              <p>{importResult.message}</p>
            )}
          </AlertDescription>
        </Alert>
      )}
    </div>
  )
} 