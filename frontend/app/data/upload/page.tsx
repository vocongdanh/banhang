"use client"

import { AppShell } from "@/components/layout/app-shell"
import { FileUploadForm } from "@/components/upload/file-upload-form"
import { FileList } from "@/components/upload/file-list"
import Link from "next/link"
import { useState } from "react"

export default function UploadPage() {
  const [refreshTrigger, setRefreshTrigger] = useState(0)
  
  const handleUploadSuccess = () => {
    // Trigger a refresh of the file list when upload is successful
    setRefreshTrigger(prev => prev + 1)
  }

  return (
    <AppShell>
      <div className="p-8">
        <div className="flex items-center mb-6">
          <Link 
            href="/data" 
            className="text-blue-500 hover:text-blue-700 mr-3"
          >
            <span className="inline-block">←</span> Quay lại
          </Link>
          <h1 className="text-3xl font-bold">Tải lên dữ liệu</h1>
        </div>

        <div className="bg-white rounded-lg shadow p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4">Tải lên tệp tin</h2>
          <p className="text-gray-600 mb-6">
            Tải lên tệp tin của bạn. Hỗ trợ các định dạng: PDF, Excel, CSV, TXT, PNG, JPG. 
            Tệp tin văn bản sẽ được lưu vào OpenAI Vector Store, 
            trong khi hình ảnh sẽ được lưu vào Qdrant.
          </p>
          
          <FileUploadForm onUploadSuccess={handleUploadSuccess} />
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold mb-4">Tệp tin đã tải lên</h2>
          <FileList refreshTrigger={refreshTrigger} />
        </div>
      </div>
    </AppShell>
  )
} 