import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { AppPreferencesProvider } from './contexts/AppPreferencesContext'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import GlobalSearch from './components/GlobalSearch'

import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import ChooseAccountTypePage from './pages/ChooseAccountTypePage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import HomePage from './pages/HomePage'
import AttributeLibraryPage from './pages/AttributeLibraryPage'
import ProfilePage from './pages/ProfilePage'
import AdminPage from './pages/AdminPage'
import PositionsPage from './pages/PositionsPage'
import PositionEditorPage from './pages/PositionEditorPage'
import PositionViewPage from './pages/PositionViewPage'
import CvDetailPage from './pages/CvDetailPage'
import PositionCvsPage from './pages/PositionCvsPage'

function App() {
    return (
        <AppPreferencesProvider>
            <AuthProvider>
                <BrowserRouter>
                    <Routes>
                        <Route
                            path="/login"
                            element={
                                <Layout>
                                    <LoginPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/forgot-password"
                            element={
                                <Layout>
                                    <ForgotPasswordPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/reset-password"
                            element={
                                <Layout>
                                    <ResetPasswordPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/register"
                            element={
                                <Layout>
                                    <RegisterPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/choose-account-type"
                            element={
                                <Layout>
                                    <ChooseAccountTypePage />
                                </Layout>
                            }
                        />

                        <Route
                            path="/"
                            element={
                                <Layout searchSlot={<GlobalSearch />}>
                                    <HomePage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/positions"
                            element={
                                <Layout searchSlot={<GlobalSearch />}>
                                    <PositionsPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/positions/new"
                            element={
                                <ProtectedRoute roles={['ROLE_RECRUITER', 'ROLE_ADMIN']}>
                                    <Layout searchSlot={<GlobalSearch />}>
                                        <PositionEditorPage />
                                    </Layout>
                                </ProtectedRoute>
                            }
                        />
                        <Route
                            path="/positions/:id/edit"
                            element={
                                <ProtectedRoute roles={['ROLE_RECRUITER', 'ROLE_ADMIN']}>
                                    <Layout searchSlot={<GlobalSearch />}>
                                        <PositionEditorPage />
                                    </Layout>
                                </ProtectedRoute>
                            }
                        />
                        <Route
                            path="/positions/:id"
                            element={
                                <Layout searchSlot={<GlobalSearch />}>
                                    <PositionViewPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/positions/:id/cvs"
                            element={
                                <ProtectedRoute roles={['ROLE_RECRUITER', 'ROLE_ADMIN']}>
                                    <Layout searchSlot={<GlobalSearch />}>
                                        <PositionCvsPage />
                                    </Layout>
                                </ProtectedRoute>
                            }
                        />
                        <Route
                            path="/cvs/:id"
                            element={
                                <Layout searchSlot={<GlobalSearch />}>
                                    <CvDetailPage />
                                </Layout>
                            }
                        />
                        <Route
                            path="/attributes"
                            element={
                                <ProtectedRoute roles={['ROLE_RECRUITER', 'ROLE_ADMIN']}>
                                    <Layout searchSlot={<GlobalSearch />}>
                                        <AttributeLibraryPage />
                                    </Layout>
                                </ProtectedRoute>
                            }
                        />
                        <Route
                            path="/profile"
                            element={
                                <ProtectedRoute>
                                    <Layout searchSlot={<GlobalSearch />}>
                                        <ProfilePage />
                                    </Layout>
                                </ProtectedRoute>
                            }
                        />
                        <Route
                            path="/admin"
                            element={
                                <ProtectedRoute roles={['ROLE_ADMIN']}>
                                    <Layout searchSlot={<GlobalSearch />}>
                                        <AdminPage />
                                    </Layout>
                                </ProtectedRoute>
                            }
                        />
                    </Routes>
                </BrowserRouter>
            </AuthProvider>
        </AppPreferencesProvider>
    )
}

export default App