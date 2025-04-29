"use client"

import { FileList } from "@/components/upload/file-list"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { UploadCloudIcon, FolderIcon, GlobeIcon, ShoppingBagIcon, CloudIcon } from "lucide-react"
import Link from "next/link"
import { DataImport } from "@/components/data/data-import"
import { VectorSearch } from "@/components/search/vector-search"
import { AppShell } from "@/components/layout/app-shell"
import { useState, useEffect } from "react"

export default function DataPage() {
  const [isLoggedIn, setIsLoggedIn] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    setIsLoggedIn(!!token);
  }, []);

  if (!isLoggedIn) {
    return (
      <AppShell>
        <div className="container mx-auto py-6">
          <div className="p-8 bg-yellow-50 border border-yellow-300 rounded-md">
            <h3 className="text-lg font-semibold mb-2">Bạn cần đăng nhập trước</h3>
            <p>Vui lòng <a href="/login" className="text-blue-600 hover:underline">đăng nhập</a> để sử dụng tính năng này.</p>
          </div>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="container mx-auto py-6 space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold">Kết nối dữ liệu</h1>
            <p className="text-muted-foreground">Quản lý và kết nối các nguồn dữ liệu cho hệ thống</p>
          </div>
        </div>

        <Tabs defaultValue="connections" className="w-full">
          <TabsList className="grid w-full grid-cols-3 md:w-[600px]">
            <TabsTrigger value="connections">Kết nối dữ liệu</TabsTrigger>
            <TabsTrigger value="files">Tệp tin đã tải</TabsTrigger>
            <TabsTrigger value="search">Tìm kiếm</TabsTrigger>
          </TabsList>
          
          <TabsContent value="connections" className="mt-6">
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {/* Upload File */}
              <Card>
                <CardHeader className="flex flex-row items-center gap-4">
                  <div className="bg-primary/10 p-3 rounded-full">
                    <UploadCloudIcon className="h-8 w-8 text-primary" />
                  </div>
                  <div>
                    <CardTitle>Upload File</CardTitle>
                    <CardDescription>
                      Tải lên tệp tin từ máy tính
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground">
                    Hỗ trợ các định dạng Excel, CSV, PDF, Word, và nhiều định dạng khác.
                  </p>
                </CardContent>
                <CardFooter>
                  <Button asChild className="w-full">
                    <Link href="/data/upload">
                      <UploadCloudIcon className="mr-2 h-4 w-4" />
                      Tải lên
                    </Link>
                  </Button>
                </CardFooter>
              </Card>

              {/* Google Drive */}
              <Card>
                <CardHeader className="flex flex-row items-center gap-4">
                  <div className="bg-blue-500/10 p-3 rounded-full">
                    <FolderIcon className="h-8 w-8 text-blue-500" />
                  </div>
                  <div>
                    <CardTitle>Google Drive</CardTitle>
                    <CardDescription>
                      Đồng bộ dữ liệu từ Google Drive
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground">
                    Kết nối tài khoản Google Drive và đồng bộ tệp tin tự động.
                  </p>
                </CardContent>
                <CardFooter>
                  <Button variant="outline" className="w-full">
                    Kết nối
                  </Button>
                </CardFooter>
              </Card>

              {/* Website */}
              <Card>
                <CardHeader className="flex flex-row items-center gap-4">
                  <div className="bg-green-500/10 p-3 rounded-full">
                    <GlobeIcon className="h-8 w-8 text-green-500" />
                  </div>
                  <div>
                    <CardTitle>Website</CardTitle>
                    <CardDescription>
                      Kết nối với website của bạn
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground">
                    Tích hợp dữ liệu từ website thông qua API hoặc web scraping.
                  </p>
                </CardContent>
                <CardFooter>
                  <Button variant="outline" className="w-full">
                    Kết nối
                  </Button>
                </CardFooter>
              </Card>

              {/* Shopee */}
              <Card>
                <CardHeader className="flex flex-row items-center gap-4">
                  <div className="bg-orange-500/10 p-3 rounded-full">
                    <ShoppingBagIcon className="h-8 w-8 text-orange-500" />
                  </div>
                  <div>
                    <CardTitle>Shopee</CardTitle>
                    <CardDescription>
                      Kết nối cửa hàng Shopee
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground">
                    Đồng bộ dữ liệu sản phẩm, đơn hàng và khách hàng từ Shopee.
                  </p>
                </CardContent>
                <CardFooter>
                  <Button variant="outline" className="w-full">
                    Kết nối
                  </Button>
                </CardFooter>
              </Card>

              {/* TikTok Shop */}
              <Card>
                <CardHeader className="flex flex-row items-center gap-4">
                  <div className="bg-black/10 p-3 rounded-full">
                    <CloudIcon className="h-8 w-8 text-black" />
                  </div>
                  <div>
                    <CardTitle>TikTok Shop</CardTitle>
                    <CardDescription>
                      Kết nối cửa hàng TikTok
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-sm text-muted-foreground">
                    Đồng bộ dữ liệu sản phẩm, đơn hàng và khách hàng từ TikTok Shop.
                  </p>
                </CardContent>
                <CardFooter>
                  <Button variant="outline" className="w-full">
                    Kết nối
                  </Button>
                </CardFooter>
              </Card>
            </div>
          </TabsContent>
          
          <TabsContent value="files" className="mt-6">
            <Card>
              <CardHeader>
                <CardTitle>Tệp tin của bạn</CardTitle>
                <CardDescription>
                  Danh sách các tệp tin đã được tải lên hệ thống.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <FileList refreshTrigger={0} />
              </CardContent>
            </Card>
          </TabsContent>
          
          <TabsContent value="search" className="mt-6">
            <Card>
              <CardHeader>
                <CardTitle>Tìm kiếm nội dung</CardTitle>
                <CardDescription>
                  Tìm kiếm thông tin trong tất cả tệp tin đã tải lên.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <VectorSearch />
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppShell>
  )
} 