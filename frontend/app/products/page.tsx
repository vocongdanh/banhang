"use client"

import { AppShell } from "@/components/layout/app-shell"

export default function ProductsPage() {
  return (
    <AppShell>
      <div className="p-8">
        <h1 className="text-3xl font-bold mb-6">Sản phẩm</h1>
        
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold mb-4">Danh sách sản phẩm</h2>
          
          <div className="overflow-x-auto">
            <table className="min-w-full border-collapse">
              <thead>
                <tr className="bg-gray-50">
                  <th className="py-3 px-4 text-left border-b">ID</th>
                  <th className="py-3 px-4 text-left border-b">Tên sản phẩm</th>
                  <th className="py-3 px-4 text-left border-b">Giá</th>
                  <th className="py-3 px-4 text-left border-b">Tồn kho</th>
                  <th className="py-3 px-4 text-left border-b">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b">1</td>
                  <td className="py-3 px-4 border-b">Sản phẩm mẫu</td>
                  <td className="py-3 px-4 border-b">100,000 VNĐ</td>
                  <td className="py-3 px-4 border-b">10</td>
                  <td className="py-3 px-4 border-b">
                    <button className="text-blue-500 hover:text-blue-700 mr-2">Xem</button>
                    <button className="text-yellow-500 hover:text-yellow-700 mr-2">Sửa</button>
                    <button className="text-red-500 hover:text-red-700">Xóa</button>
                  </td>
                </tr>
                <tr className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b">2</td>
                  <td className="py-3 px-4 border-b">Sản phẩm mẫu 2</td>
                  <td className="py-3 px-4 border-b">200,000 VNĐ</td>
                  <td className="py-3 px-4 border-b">5</td>
                  <td className="py-3 px-4 border-b">
                    <button className="text-blue-500 hover:text-blue-700 mr-2">Xem</button>
                    <button className="text-yellow-500 hover:text-yellow-700 mr-2">Sửa</button>
                    <button className="text-red-500 hover:text-red-700">Xóa</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppShell>
  )
} 