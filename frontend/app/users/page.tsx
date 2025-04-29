"use client"

import { AppShell } from "@/components/layout/app-shell"

export default function UsersPage() {
  return (
    <AppShell>
      <div className="p-8">
        <h1 className="text-3xl font-bold mb-6">Người dùng</h1>
        
        <div className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold mb-4">Danh sách người dùng</h2>
          
          <div className="overflow-x-auto">
            <table className="min-w-full border-collapse">
              <thead>
                <tr className="bg-gray-50">
                  <th className="py-3 px-4 text-left border-b">ID</th>
                  <th className="py-3 px-4 text-left border-b">Tên</th>
                  <th className="py-3 px-4 text-left border-b">Email</th>
                  <th className="py-3 px-4 text-left border-b">Vai trò</th>
                  <th className="py-3 px-4 text-left border-b">Ngày tạo</th>
                  <th className="py-3 px-4 text-left border-b">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b">1</td>
                  <td className="py-3 px-4 border-b">Admin</td>
                  <td className="py-3 px-4 border-b">admin@example.com</td>
                  <td className="py-3 px-4 border-b">
                    <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">Admin</span>
                  </td>
                  <td className="py-3 px-4 border-b">10/01/2025</td>
                  <td className="py-3 px-4 border-b">
                    <button className="text-blue-500 hover:text-blue-700 mr-2">Xem</button>
                    <button className="text-yellow-500 hover:text-yellow-700 mr-2">Sửa</button>
                  </td>
                </tr>
                <tr className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b">2</td>
                  <td className="py-3 px-4 border-b">Người dùng</td>
                  <td className="py-3 px-4 border-b">user@example.com</td>
                  <td className="py-3 px-4 border-b">
                    <span className="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Người dùng</span>
                  </td>
                  <td className="py-3 px-4 border-b">15/02/2025</td>
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